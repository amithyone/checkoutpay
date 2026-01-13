<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Business;
use App\Models\ProcessedEmail;
use App\Jobs\CheckPaymentEmails;
use App\Services\AccountNumberService;
use App\Services\TransactionLogService;
use App\Services\PaymentMatchingService;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PaymentService
{
    public function __construct(
        protected AccountNumberService $accountNumberService,
        protected TransactionLogService $transactionLogService,
        protected PaymentMatchingService $paymentMatchingService
    ) {}

    /**
     * Create a new payment request
     */
    public function createPayment(array $data, ?Business $business = null, ?Request $request = null): Payment
    {
        // Generate transaction ID if not provided
        if (empty($data['transaction_id'])) {
            $data['transaction_id'] = $this->generateTransactionId();
        }

        // Normalize payer name (ensure it's set from 'name' field if provided)
        if (!empty($data['name']) && empty($data['payer_name'])) {
            $data['payer_name'] = $data['name'];
        }
        
        // Normalize payer name
        if (!empty($data['payer_name'])) {
            $data['payer_name'] = strtolower(trim($data['payer_name']));
        }

        // Set expiration time from settings (transaction_pending_time_minutes)
        // Default: 24 hours (1440 minutes) if setting not found
        $pendingTimeMinutes = \App\Models\Setting::get('transaction_pending_time_minutes', 1440);
        $expiresAt = now()->addMinutes($pendingTimeMinutes);

        // Assign account number if not provided
        $accountNumber = null;
        $assignedAccount = null;
        if (empty($data['account_number'])) {
            $assignedAccount = $this->accountNumberService->assignAccountNumber($business);
            if ($assignedAccount) {
                $accountNumber = $assignedAccount->account_number;
                $assignedAccount->incrementUsage();
            }
        } else {
            $accountNumber = $data['account_number'];
        }

        // Normalize webhook URL to prevent double slashes
        $webhookUrl = $data['webhook_url'] ?? null;
        if ($webhookUrl) {
            $webhookUrl = preg_replace('#([^:])//+#', '$1/', $webhookUrl); // Fix double slashes but preserve http:// or https://
        }

        $payment = Payment::create([
            'transaction_id' => $data['transaction_id'],
            'amount' => $data['amount'],
            'payer_name' => $data['payer_name'] ?? null,
            'bank' => $data['bank'] ?? null,
            'webhook_url' => $webhookUrl,
            'account_number' => $accountNumber,
            'business_id' => $business?->id,
            'status' => Payment::STATUS_PENDING,
            'expires_at' => $expiresAt,
        ]);

        // Log payment request
        $this->transactionLogService->logPaymentRequest($payment, $request);

        // Log account assignment if account was assigned
        if ($accountNumber && $assignedAccount) {
            $this->transactionLogService->logAccountAssignment($payment, $assignedAccount);
        }

        \Illuminate\Support\Facades\Log::info('Payment request created', [
            'transaction_id' => $payment->transaction_id,
            'amount' => $payment->amount,
            'payer_name' => $payment->payer_name,
        ]);

        // Check stored emails for immediate match
        $this->checkStoredEmailsForMatch($payment);

        // Schedule job to check for matching emails after 1 minute
        // This allows time for emails to arrive and be processed
        CheckPaymentEmails::dispatch($payment)
            ->delay(now()->addMinute());

        \Illuminate\Support\Facades\Log::info('Scheduled email check job for payment', [
            'transaction_id' => $payment->transaction_id,
            'scheduled_at' => now()->addMinute()->toDateTimeString(),
        ]);

        return $payment;
    }

    /**
     * Generate a unique transaction ID
     * Format: TXN-{timestamp}-{random}
     * Ensures uniqueness by checking database
     */
    protected function generateTransactionId(): string
    {
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $transactionId = 'TXN-' . now()->timestamp . '-' . Str::random(9);
            $exists = Payment::where('transaction_id', $transactionId)->exists();
            $attempt++;
        } while ($exists && $attempt < $maxAttempts);

        if ($exists) {
            // Fallback: add microsecond timestamp for extra uniqueness
            $transactionId = 'TXN-' . now()->timestamp . '-' . now()->micro . '-' . Str::random(6);
        }

        return $transactionId;
    }

    /**
     * Check stored emails for immediate match when payment is created
     */
    protected function checkStoredEmailsForMatch(Payment $payment): void
    {
        try {
            // Get unmatched stored emails with matching amount
            // CRITICAL: Only check emails received AFTER transaction creation
            $storedEmails = ProcessedEmail::unmatched()
                ->withAmount($payment->amount)
                ->where('email_date', '>=', $payment->created_at) // Email must be AFTER transaction creation
                ->get();
            
            foreach ($storedEmails as $storedEmail) {
                // Re-extract from html_body if available
                $emailData = [
                    'subject' => $storedEmail->subject,
                    'from' => $storedEmail->from_email,
                    'text' => $storedEmail->text_body ?? '',
                    'html' => $storedEmail->html_body ?? '', // Use html_body for matching
                    'date' => $storedEmail->email_date ? $storedEmail->email_date->toDateTimeString() : null,
                ];
                
                // Re-extract payment info (will use html_body)
                $extractionResult = $this->paymentMatchingService->extractPaymentInfo($emailData);
                $extractedInfo = $extractionResult['data'] ?? null;
                
                if (!$extractedInfo || !isset($extractedInfo['amount']) || !$extractedInfo['amount']) {
                    continue;
                }
                
                $emailDate = $storedEmail->email_date ? Carbon::parse($storedEmail->email_date) : null;
                $match = $this->paymentMatchingService->matchPayment($payment, $extractedInfo, $emailDate);
                
                if ($match['matched']) {
                    // Mark stored email as matched
                    $storedEmail->markAsMatched($payment);
                    
                    // Approve payment
                    $payment->approve([
                        'subject' => $storedEmail->subject,
                        'from' => $storedEmail->from_email,
                        'text' => $storedEmail->text_body,
                        'html' => $storedEmail->html_body,
                        'date' => $storedEmail->email_date->toDateTimeString(),
                        'sender_name' => $storedEmail->sender_name, // Map sender_name to payer_name
                    ]);
                    
                    // Update business balance
                    if ($payment->business_id) {
                        $payment->business->increment('balance', $payment->amount);
                    }
                    
                    // Dispatch event to send webhook
                    event(new \App\Events\PaymentApproved($payment));
                    
                    \Illuminate\Support\Facades\Log::info('Payment matched from stored email on creation', [
                        'transaction_id' => $payment->transaction_id,
                        'stored_email_id' => $storedEmail->id,
                    ]);
                    
                    break; // Only match one email per payment
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error checking stored emails for match', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
            ]);
        }
    }
}

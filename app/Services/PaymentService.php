<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Business;
use App\Models\ProcessedEmail;
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

        // Normalize payer name
        if (!empty($data['payer_name'])) {
            $data['payer_name'] = strtolower(trim($data['payer_name']));
        }

        // Set expiration time (default 24 hours, configurable)
        $expirationHours = config('payment.expiration_hours', 24);
        $expiresAt = now()->addHours($expirationHours);

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

        $payment = Payment::create([
            'transaction_id' => $data['transaction_id'],
            'amount' => $data['amount'],
            'payer_name' => $data['payer_name'] ?? null,
            'bank' => $data['bank'] ?? null,
            'webhook_url' => $data['webhook_url'],
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
            $storedEmails = ProcessedEmail::unmatched()
                ->withAmount($payment->amount)
                ->get();
            
            foreach ($storedEmails as $storedEmail) {
                $match = $this->paymentMatchingService->matchPayment($payment, [
                    'amount' => $storedEmail->amount,
                    'sender_name' => $storedEmail->sender_name,
                ]);
                
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

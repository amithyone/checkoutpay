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

        // Identify website from multiple sources (in priority order)
        $websiteId = null;
        
        // 1. Allow explicit website_id override (highest priority)
        if (!empty($data['business_website_id'])) {
            // Verify the website belongs to this business
            $website = \App\Models\BusinessWebsite::where('id', $data['business_website_id'])
                ->where('business_id', $business->id)
                ->first();
            if ($website) {
                $websiteId = $website->id;
            }
        }
        // 2. Try to identify from explicit website_url parameter
        elseif ($business && !empty($data['website_url'])) {
            $websiteId = $this->identifyWebsiteFromUrl($data['website_url'], $business);
        }
        // 3. Try to identify from webhook_url or return_url
        elseif ($business && ($webhookUrl || !empty($data['return_url']))) {
            $urlToCheck = $webhookUrl ?? $data['return_url'];
            $websiteId = $this->identifyWebsiteFromUrl($urlToCheck, $business);
        }
        // 4. Try to identify from HTTP referer header
        elseif ($business && $request && $request->header('referer')) {
            $referer = $request->header('referer');
            $websiteId = $this->identifyWebsiteFromUrl($referer, $business);
        }
        // 5. Try to identify from Origin header
        elseif ($business && $request && $request->header('origin')) {
            $origin = $request->header('origin');
            $websiteId = $this->identifyWebsiteFromUrl($origin, $business);
        }
        // 6. If only one approved website exists, use that as default
        elseif ($business) {
            $approvedWebsites = $business->approvedWebsites;
            if ($approvedWebsites->count() === 1) {
                $websiteId = $approvedWebsites->first()->id;
            }
        }

        // Log website identification for debugging
        if ($business) {
            if (!$websiteId) {
                \Illuminate\Support\Facades\Log::debug('Website identification failed', [
                    'business_id' => $business->id,
                    'webhook_url' => $webhookUrl,
                    'return_url' => $data['return_url'] ?? null,
                    'referer' => $request?->header('referer'),
                    'approved_websites_count' => $business->approvedWebsites->count(),
                ]);
            } else {
                \Illuminate\Support\Facades\Log::debug('Website identified successfully', [
                    'business_id' => $business->id,
                    'website_id' => $websiteId,
                    'source' => !empty($data['business_website_id']) ? 'explicit' : 
                               ($webhookUrl || !empty($data['return_url']) ? 'url' : 
                               ($request?->header('referer') ? 'referer' : 'single_website_fallback')),
                ]);
            }
        }

        $payment = Payment::create([
            'transaction_id' => $data['transaction_id'],
            'amount' => $data['amount'],
            'payer_name' => $data['payer_name'] ?? null,
            'bank' => $data['bank'] ?? null,
            'webhook_url' => $webhookUrl,
            'account_number' => $accountNumber,
            'business_id' => $business?->id,
            'business_website_id' => $websiteId,
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
                        
                        // Send new deposit notification
                        $payment->business->notify(new \App\Notifications\NewDepositNotification($payment));
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

    /**
     * Identify website from URL by matching against approved websites
     */
    protected function identifyWebsiteFromUrl(string $url, Business $business): ?int
    {
        if (empty($url)) {
            return null;
        }

        $parsedUrl = parse_url($url);
        $urlHost = $parsedUrl['host'] ?? null;
        
        if (!$urlHost) {
            // If no host, try to extract from the URL itself
            $urlHost = preg_replace('#^https?://#', '', $url);
            $urlHost = preg_replace('#/.*$#', '', $urlHost);
        }

        if (!$urlHost) {
            return null;
        }

        // Normalize host: remove www. prefix and convert to lowercase
        $urlHost = strtolower(preg_replace('/^www\./', '', $urlHost));

        // Check against all approved websites
        $approvedWebsites = $business->approvedWebsites;
        
        foreach ($approvedWebsites as $website) {
            $websiteUrl = $website->website_url;
            $websiteHost = parse_url($websiteUrl, PHP_URL_HOST);
            
            if (!$websiteHost) {
                // If no host in website_url, try to extract it
                $websiteHost = preg_replace('#^https?://#', '', $websiteUrl);
                $websiteHost = preg_replace('#/.*$#', '', $websiteHost);
            }
            
            if ($websiteHost) {
                // Normalize website host
                $websiteHost = strtolower(preg_replace('/^www\./', '', $websiteHost));
                
                // Exact match
                if ($urlHost === $websiteHost) {
                    return $website->id;
                }
                
                // Subdomain match (e.g., api.example.com matches example.com)
                $urlParts = explode('.', $urlHost);
                $websiteParts = explode('.', $websiteHost);
                
                // Check if URL host ends with website host (for subdomain matching)
                if (count($urlParts) >= count($websiteParts)) {
                    $urlSuffix = implode('.', array_slice($urlParts, -count($websiteParts)));
                    if ($urlSuffix === $websiteHost) {
                        return $website->id;
                    }
                }
            }
        }

        return null;
    }
}

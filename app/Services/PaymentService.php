<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        protected AccountNumberService $accountNumberService
    ) {}

    /**
     * Create a payment request
     */
    public function createPayment(array $data, Business $business, ?Request $request = null, bool $isInvoice = false): Payment
    {
        // Generate transaction ID if not provided
        $transactionId = $data['transaction_id'] ?? $this->generateTransactionId();

        // Assign account number - use invoice pool if this is an invoice payment
        if ($isInvoice) {
            $accountNumber = $this->accountNumberService->assignInvoiceAccountNumber($business);
            // Invalidate invoice pool cache
            $this->accountNumberService->invalidateInvoicePoolCache();
        } else {
            $accountNumber = $this->accountNumberService->assignAccountNumber($business);
            // Invalidate regular pool cache
            $this->accountNumberService->invalidatePendingAccountsCache();
        }
        
        if (!$accountNumber) {
            throw new \Exception('No available account number found. Please contact support.');
        }

        // Create payment
        $payment = Payment::create([
            'transaction_id' => $transactionId,
            'amount' => $data['amount'],
            'payer_name' => $data['payer_name'] ?? null,
            'bank' => $data['bank'] ?? null,
            'webhook_url' => $data['webhook_url'],
            'account_number' => $accountNumber->account_number,
            'business_id' => $business->id,
            'status' => Payment::STATUS_PENDING,
            'email_data' => $this->buildEmailData($data, $request),
            'expires_at' => now()->addHours(24), // Payments expire after 24 hours
        ]);

        // CRITICAL: Ensure account_number is set (safeguard against database issues)
        if (!$payment->account_number) {
            Log::error('Payment created without account_number - attempting to assign', [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'business_id' => $business->id,
                'assigned_account_number' => $accountNumber->account_number,
            ]);
            
            // Try to assign account number again
            $retryAccountNumber = $isInvoice 
                ? $this->accountNumberService->assignInvoiceAccountNumber($business)
                : $this->accountNumberService->assignAccountNumber($business);
            
            if ($retryAccountNumber) {
                $payment->update(['account_number' => $retryAccountNumber->account_number]);
                Log::warning('Account number assigned retroactively to payment', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'account_number' => $retryAccountNumber->account_number,
                ]);
            } else {
                Log::error('CRITICAL: Unable to assign account number to payment after creation', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'business_id' => $business->id,
                ]);
                throw new \Exception('Payment created but account number assignment failed. Payment ID: ' . $payment->id);
            }
        }

        // Set website if provided
        if (isset($data['business_website_id'])) {
            $payment->update(['business_website_id' => $data['business_website_id']]);
        } elseif (isset($data['website_url']) || isset($data['return_url'])) {
            // Try to identify website from URL
            $websiteUrl = $data['website_url'] ?? $data['return_url'] ?? null;
            if ($websiteUrl) {
                $website = $business->websites()->where('website_url', 'like', '%' . parse_url($websiteUrl, PHP_URL_HOST) . '%')->first();
                if ($website) {
                    $payment->update(['business_website_id' => $website->id]);
                }
            }
        }

        // Refresh to ensure we have the latest data
        $payment->refresh();

        Log::info('Payment created', [
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'business_id' => $business->id,
            'amount' => $payment->amount,
            'account_number' => $payment->account_number,
        ]);

        return $payment;
    }

    /**
     * Generate unique transaction ID
     */
    protected function generateTransactionId(): string
    {
        do {
            $transactionId = 'TXN' . strtoupper(Str::random(12));
        } while (Payment::where('transaction_id', $transactionId)->exists());

        return $transactionId;
    }

    /**
     * Build email data from request
     */
    protected function buildEmailData(array $data, ?Request $request): array
    {
        $emailData = [];

        if (isset($data['service'])) {
            $emailData['service'] = $data['service'];
        }

        if (isset($data['return_url'])) {
            $emailData['return_url'] = $data['return_url'];
        }

        if ($request) {
            $emailData['ip_address'] = $request->ip();
            $emailData['user_agent'] = $request->userAgent();
        }

        return $emailData ?: [];
    }
}

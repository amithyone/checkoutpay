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
     * Normalize payer name for comparison (trim, lowercase, collapse spaces).
     */
    public static function normalizePayerName(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }
        $normalized = trim(preg_replace('/\s+/', ' ', $name));
        return strtolower($normalized);
    }

    /**
     * Find an existing pending payment for the same business, amount, and similar payer name.
     * Reusing it reduces duplicate account numbers when users retry without paying.
     */
    protected function findReusablePendingPayment(array $data, Business $business): ?Payment
    {
        $amount = (float) ($data['amount'] ?? 0);
        $payerName = $data['payer_name'] ?? null;
        if ($amount < 0.01 || !$payerName || trim((string) $payerName) === '') {
            return null;
        }

        $normalizedName = self::normalizePayerName($payerName);
        if ($normalizedName === '') {
            return null;
        }

        $tolerance = 0.01;
        $candidates = Payment::pending()
            ->where('business_id', $business->id)
            ->whereBetween('amount', [$amount - $tolerance, $amount + $tolerance])
            ->whereNotNull('account_number')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        foreach ($candidates as $payment) {
            if (self::normalizePayerName($payment->payer_name) === $normalizedName) {
                return $payment;
            }
        }

        return null;
    }

    /**
     * Create a payment request
     */
    public function createPayment(array $data, Business $business, ?Request $request = null, bool $isInvoice = false): Payment
    {
        $isMembership = isset($data['service']) && $data['service'] === 'membership';

        // For regular (non-invoice, non-membership) payments: reuse existing pending if same name + amount
        if (!$isInvoice && !$isMembership) {
            $existing = $this->findReusablePendingPayment($data, $business);
            if ($existing) {
                $existing->load('accountNumberDetails');
                $emailData = array_merge($existing->email_data ?? [], $this->buildEmailData($data, $request));
                $updatePayload = ['email_data' => $emailData];
                if (!empty($data['webhook_url'])) {
                    $updatePayload['webhook_url'] = $data['webhook_url'];
                }
                $existing->update($updatePayload);
                // Extend expiry so they have another 24h to pay
                $existing->update(['expires_at' => now()->addHours(24)]);
                Log::info('Reused existing pending payment (same name + amount)', [
                    'payment_id' => $existing->id,
                    'transaction_id' => $existing->transaction_id,
                    'business_id' => $business->id,
                    'amount' => $existing->amount,
                    'payer_name' => $existing->payer_name,
                ]);
                return $existing;
            }
        }

        // Generate transaction ID if not provided
        $transactionId = $data['transaction_id'] ?? $this->generateTransactionId();

        // Assign account number based on payment type
        if ($isInvoice) {
            // Invoice payments use invoice pool
            $accountNumber = $this->accountNumberService->assignInvoiceAccountNumber($business);
            $this->accountNumberService->invalidateInvoicePoolCache();
        } elseif ($isMembership) {
            // Membership payments use membership pool
            $accountNumber = $this->accountNumberService->assignMembershipAccountNumber($business);
            $this->accountNumberService->invalidateMembershipPoolCache();
        } else {
            // Regular payments use regular pool
            $accountNumber = $this->accountNumberService->assignAccountNumber($business);
            $this->accountNumberService->invalidatePendingAccountsCache();
        }
        
        if (!$accountNumber) {
            throw new \Exception('No available account number found. Please contact support.');
        }

        // Create payment
        // Invoice payments never expire - they remain active until paid
        // Regular payments expire after 24 hours
        $expiresAt = $isInvoice ? null : now()->addHours(24);
        
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
            'expires_at' => $expiresAt,
        ]);

        // Set website if provided
        if (isset($data['business_website_id'])) {
            $payment->update(['business_website_id' => $data['business_website_id']]);
        } else {
            // Try to identify website from various URL sources
            $website = null;
            
            // Priority 1: Try website_url or return_url from data
            $websiteUrl = $data['website_url'] ?? $data['return_url'] ?? null;
            if ($websiteUrl) {
                $website = $business->websites()
                    ->where('website_url', 'like', '%' . parse_url($websiteUrl, PHP_URL_HOST) . '%')
                    ->where('is_approved', true)
                    ->first();
            }
            
            // Priority 2: Try webhook_url from data (if website not found yet)
            if (!$website && isset($data['webhook_url'])) {
                $webhookHost = parse_url($data['webhook_url'], PHP_URL_HOST);
                if ($webhookHost) {
                    $website = $business->websites()
                        ->where(function($q) use ($webhookHost) {
                            $q->where('website_url', 'like', '%' . $webhookHost . '%')
                              ->orWhere('webhook_url', 'like', '%' . $webhookHost . '%');
                        })
                        ->where('is_approved', true)
                        ->first();
                }
            }
            
            // Priority 3: Try webhook_url from payment (after creation)
            if (!$website && $payment->webhook_url) {
                $webhookHost = parse_url($payment->webhook_url, PHP_URL_HOST);
                if ($webhookHost) {
                    $website = $business->websites()
                        ->where(function($q) use ($webhookHost) {
                            $q->where('website_url', 'like', '%' . $webhookHost . '%')
                              ->orWhere('webhook_url', 'like', '%' . $webhookHost . '%');
                        })
                        ->where('is_approved', true)
                        ->first();
                }
            }
            
            if ($website) {
                $payment->update(['business_website_id' => $website->id]);
            }
        }
        
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
                $updateData = ['account_number' => $retryAccountNumber->account_number];
                
                // Also ensure website is set if still null
                if (!$payment->business_website_id) {
                    $website = $this->identifyWebsiteFromPayment($payment, $business, $data);
                    if ($website) {
                        $updateData['business_website_id'] = $website->id;
                    }
                }
                
                $payment->update($updateData);
                Log::warning('Account number assigned retroactively to payment', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'account_number' => $retryAccountNumber->account_number,
                    'website_id' => $updateData['business_website_id'] ?? null,
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
    
    /**
     * Identify website from payment data
     */
    protected function identifyWebsiteFromPayment(Payment $payment, Business $business, array $data)
    {
        // Priority 1: Try webhook_url from payment
        if ($payment->webhook_url) {
            $webhookHost = parse_url($payment->webhook_url, PHP_URL_HOST);
            if ($webhookHost) {
                $website = $business->websites()
                    ->where(function($q) use ($webhookHost) {
                        $q->where('website_url', 'like', '%' . $webhookHost . '%')
                          ->orWhere('webhook_url', 'like', '%' . $webhookHost . '%');
                    })
                    ->where('is_approved', true)
                    ->first();
                
                if ($website) {
                    return $website;
                }
            }
        }
        
        // Priority 2: Try webhook_url from data
        if (isset($data['webhook_url'])) {
            $webhookHost = parse_url($data['webhook_url'], PHP_URL_HOST);
            if ($webhookHost) {
                $website = $business->websites()
                    ->where(function($q) use ($webhookHost) {
                        $q->where('website_url', 'like', '%' . $webhookHost . '%')
                          ->orWhere('webhook_url', 'like', '%' . $webhookHost . '%');
                    })
                    ->where('is_approved', true)
                    ->first();
                
                if ($website) {
                    return $website;
                }
            }
        }
        
        // Priority 3: Try from data (website_url or return_url)
        $url = $data['website_url'] ?? $data['return_url'] ?? null;
        if ($url) {
            $urlHost = parse_url($url, PHP_URL_HOST);
            if ($urlHost) {
                $website = $business->websites()
                    ->where('website_url', 'like', '%' . $urlHost . '%')
                    ->where('is_approved', true)
                    ->first();
                
                if ($website) {
                    return $website;
                }
            }
        }
        
        // Priority 4: Try from email_data
        $emailData = $payment->email_data ?? [];
        $url = $emailData['return_url'] ?? $emailData['website_url'] ?? null;
        if ($url) {
            $urlHost = parse_url($url, PHP_URL_HOST);
            if ($urlHost) {
                $website = $business->websites()
                    ->where('website_url', 'like', '%' . $urlHost . '%')
                    ->where('is_approved', true)
                    ->first();
                
                if ($website) {
                    return $website;
                }
            }
        }
        
        // Priority 5: If business has only one approved website, use that
        $approvedWebsites = $business->websites()->where('is_approved', true)->get();
        if ($approvedWebsites->count() === 1) {
            return $approvedWebsites->first();
        }
        
        return null;
    }
}

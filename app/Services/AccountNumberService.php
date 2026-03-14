<?php

namespace App\Services;

use App\Models\AccountNumber;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AccountNumberService
{
    /**
     * Cache key for pending account numbers
     */
    const CACHE_KEY_PENDING_ACCOUNTS = 'account_number_service:pending_accounts';
    
    /**
     * Cache key for last used account
     */
    const CACHE_KEY_LAST_USED_ACCOUNT = 'account_number_service:last_used_account';
    
    /**
     * Cache key for pool accounts list
     */
    const CACHE_KEY_POOL_ACCOUNTS = 'account_number_service:pool_accounts';
    
    /**
     * Cache key for invoice pool accounts list
     */
    const CACHE_KEY_INVOICE_POOL_ACCOUNTS = 'account_number_service:invoice_pool_accounts';
    
    /**
     * Cache key for last used invoice account
     */
    const CACHE_KEY_LAST_USED_INVOICE_ACCOUNT = 'account_number_service:last_used_invoice_account';
    
    /**
     * Cache key for membership pool accounts list
     */
    const CACHE_KEY_MEMBERSHIP_POOL_ACCOUNTS = 'account_number_service:membership_pool_accounts';
    
    /**
     * Cache key for last used membership account
     */
    const CACHE_KEY_LAST_USED_MEMBERSHIP_ACCOUNT = 'account_number_service:last_used_membership_account';
    
    /**
     * Cache key for tickets pool accounts list
     */
    const CACHE_KEY_TICKETS_POOL_ACCOUNTS = 'account_number_service:tickets_pool_accounts';
    
    /**
     * Cache TTL in seconds (300 seconds = 5 minutes)
     * Increased from 60s to 300s to prevent frequent cache expiration
     */
    const CACHE_TTL = 300;

    /**
     * Assign account number to payment request.
     * Priority: Business-specific > Same-payer reuse (by name similarity) > First available in pool (by id, first to last).
     * An account is "released" for new use release_after_success_minutes after it sees a successful transaction.
     */
    public function assignAccountNumber(?Business $business = null, ?string $payerName = null): ?AccountNumber
    {
        $startTime = microtime(true);

        // Try to get business-specific account number
        if ($business && $business->hasAccountNumber()) {
            $accountNumber = $business->primaryAccountNumber();
            $duration = (microtime(true) - $startTime) * 1000;
            Log::info('Assigned business-specific account number', [
                'business_id' => $business->id,
                'account_number' => $accountNumber->account_number,
                'duration_ms' => round($duration, 2),
            ]);
            return $accountNumber;
        }

        $poolAccounts = Cache::remember(self::CACHE_KEY_POOL_ACCOUNTS, self::CACHE_TTL, function () {
            return AccountNumber::pool()
                ->active()
                ->orderBy('id')
                ->get();
        });

        if ($poolAccounts->isEmpty()) {
            Log::warning('No available pool account number found');
            return null;
        }

        $releaseMinutes = $this->getReleaseAfterSuccessMinutes();
        $poolNumbers = $poolAccounts->pluck('account_number')->toArray();
        $poolAccountsByNumber = $poolAccounts->keyBy('account_number');

        // Same-payer reuse: if payer name given, try to reuse same account for same person (within release window)
        if ($payerName !== null && $payerName !== '') {
            $reused = $this->trySamePayerReuse($payerName, $poolNumbers, $releaseMinutes);
            if ($reused !== null && isset($poolAccountsByNumber[$reused])) {
                $account = $poolAccountsByNumber[$reused];
                $duration = (microtime(true) - $startTime) * 1000;
                Log::info('Assigned pool account number (same-payer reuse)', [
                    'business_id' => $business?->id,
                    'account_number' => $account->account_number,
                    'payer_name' => $payerName,
                    'duration_ms' => round($duration, 2),
                ]);
                return $account;
            }
        }

        // In-use = pending (non-expired) union recently approved (matched within release window)
        $pendingAccountNumbers = $this->getPendingAccountNumbers();
        $recentlyApproved = $this->getRecentlyApprovedAccountNumbers($releaseMinutes);
        $inUseSet = array_flip(array_merge($pendingAccountNumbers, $recentlyApproved));

        // Pool order: first to last by id. Pick first account (by id) that is not in use.
        $selectedAccount = null;
        foreach ($poolAccounts as $account) {
            if (!isset($inUseSet[$account->account_number])) {
                $selectedAccount = $account;
                break;
            }
        }

        // If all in use, wrap: use next after last used (by creation order of pending)
        $lastUsedAccountNumber = $this->getLastUsedAccountNumber();
        if (!$selectedAccount) {
            $poolAccountsArray = $poolAccounts->values()->all();
            $poolCount = count($poolAccountsArray);
            $lastUsedAccountId = $lastUsedAccountNumber && isset($poolAccountsByNumber[$lastUsedAccountNumber])
                ? $poolAccountsByNumber[$lastUsedAccountNumber]->id
                : null;
            $startIndex = 0;
            if ($lastUsedAccountId) {
                $lastUsedIndex = array_search($lastUsedAccountId, array_column($poolAccountsArray, 'id'));
                if ($lastUsedIndex !== false) {
                    $startIndex = ($lastUsedIndex + 1) % $poolCount;
                }
            }
            $selectedAccount = $poolAccountsArray[$startIndex];
        }

        $duration = (microtime(true) - $startTime) * 1000;
        Log::info('Assigned pool account number (first available by id)', [
            'business_id' => $business?->id,
            'account_number' => $selectedAccount->account_number,
            'account_id' => $selectedAccount->id,
            'pool_size' => $poolAccounts->count(),
            'in_use_count' => count($inUseSet),
            'duration_ms' => round($duration, 2),
        ]);

        return $selectedAccount;
    }

    /**
     * Try to reuse the same account for the same payer (by name similarity).
     * Returns account_number string if reuse found, null otherwise.
     */
    protected function trySamePayerReuse(string $payerName, array $poolNumbers, int $releaseMinutes): ?string
    {
        $threshold = $this->getSamePayerSimilarityPercent();
        $cutoff = now()->subMinutes($releaseMinutes);

        $candidate = Payment::whereNotNull('account_number')
            ->whereIn('account_number', $poolNumbers)
            ->where(function ($q) use ($cutoff, $releaseMinutes) {
                $q->where('status', Payment::STATUS_PENDING)
                    ->orWhere(function ($q2) use ($cutoff) {
                        $q2->where('status', Payment::STATUS_APPROVED)
                            ->whereNotNull('matched_at')
                            ->where('matched_at', '>=', $cutoff);
                    });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($candidate as $payment) {
            if ($payment->payer_name && $this->isSamePayer($payerName, $payment->payer_name, $threshold)) {
                return $payment->account_number;
            }
        }

        return null;
    }

    protected function getReleaseAfterSuccessMinutes(): int
    {
        return (int) Setting::get('account_release_after_success_minutes', 30);
    }

    protected function getSamePayerSimilarityPercent(): int
    {
        return (int) Setting::get('account_same_payer_similarity_percent', 70);
    }

    /**
     * Name similarity check (same person). Uses similar_text; threshold is 50-100%.
     */
    protected function isSamePayer(string $name1, string $name2, int $thresholdPercent = 70): bool
    {
        $n1 = strtolower(trim(preg_replace('/\s+/', ' ', $name1)));
        $n2 = strtolower(trim(preg_replace('/\s+/', ' ', $name2)));
        if ($n1 === '' || $n2 === '') {
            return false;
        }
        if ($n1 === $n2) {
            return true;
        }
        $similarity = 0.0;
        similar_text($n1, $n2, $similarity);
        return $similarity >= $thresholdPercent;
    }

    /**
     * Account numbers that have an approved payment with matched_at within the last $releaseMinutes.
     * These are still "in use" and not released for another payer.
     */
    protected function getRecentlyApprovedAccountNumbers(int $releaseMinutes): array
    {
        $cutoff = now()->subMinutes($releaseMinutes);
        return Payment::where('status', Payment::STATUS_APPROVED)
            ->whereNotNull('account_number')
            ->whereNotNull('matched_at')
            ->where('matched_at', '>=', $cutoff)
            ->pluck('account_number')
            ->unique()
            ->values()
            ->toArray();
    }
    
    /**
     * Get pending account numbers (cached)
     * OPTIMIZED: Uses cache to avoid querying all pending payments on every request
     */
    protected function getPendingAccountNumbers(): array
    {
        return Cache::remember(self::CACHE_KEY_PENDING_ACCOUNTS, self::CACHE_TTL, function () {
            if (!class_exists(Payment::class)) {
                return [];
            }
            
            return Payment::where('status', Payment::STATUS_PENDING)
                ->whereNotNull('account_number')
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->pluck('account_number')
                ->unique()
                ->toArray();
        });
    }
    
    /**
     * Get last used account number (cached)
     * OPTIMIZED: Uses cache to avoid querying on every request
     */
    protected function getLastUsedAccountNumber(): ?string
    {
        return Cache::remember(self::CACHE_KEY_LAST_USED_ACCOUNT, self::CACHE_TTL, function () {
            if (!class_exists(Payment::class)) {
                return null;
            }
            
            $lastPayment = Payment::where('status', Payment::STATUS_PENDING)
                ->whereNotNull('account_number')
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->orderBy('created_at', 'desc')
                ->first();
            
            return $lastPayment ? $lastPayment->account_number : null;
        });
    }
    
    /**
     * Invalidate cache for pending account numbers
     * Call this when payments are created or approved
     */
    public function invalidatePendingAccountsCache(): void
    {
        Cache::forget(self::CACHE_KEY_PENDING_ACCOUNTS);
        Cache::forget(self::CACHE_KEY_LAST_USED_ACCOUNT);
        Cache::forget(self::CACHE_KEY_POOL_ACCOUNTS);
        // Also invalidate invoice pool cache when regular pool cache is invalidated
        $this->invalidateInvoicePoolCache();
    }

    /**
     * Move account numbers that should be in pool (business_id is null but is_pool is false)
     */
    public function moveOrphanedAccountsToPool(): void
    {
        $orphanedAccounts = AccountNumber::shouldBeInPool()->get();
        
        if ($orphanedAccounts->isNotEmpty()) {
            AccountNumber::shouldBeInPool()->update(['is_pool' => true]);
            
            Log::info('Moved orphaned account numbers to pool', [
                'count' => $orphanedAccounts->count(),
                'account_numbers' => $orphanedAccounts->pluck('account_number')->toArray(),
            ]);
        }
    }

    /**
     * Get account number details for display
     */
    public function getAccountDetails(?AccountNumber $accountNumber): ?array
    {
        if (!$accountNumber) {
            return null;
        }

        return [
            'account_number' => $accountNumber->account_number,
            'account_name' => $accountNumber->account_name,
            'bank_name' => $accountNumber->bank_name,
            'is_pool' => $accountNumber->is_pool,
        ];
    }

    /**
     * Create pool account number
     */
    public function createPoolAccount(array $data): AccountNumber
    {
        return AccountNumber::create([
            'account_number' => $data['account_number'],
            'account_name' => $data['account_name'],
            'bank_name' => $data['bank_name'],
            'is_pool' => true,
            'is_invoice_pool' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Create invoice pool account number
     */
    public function createInvoicePoolAccount(array $data): AccountNumber
    {
        return AccountNumber::create([
            'account_number' => $data['account_number'],
            'account_name' => $data['account_name'],
            'bank_name' => $data['bank_name'],
            'is_pool' => false, // Invoice pool accounts are separate from regular pool
            'is_invoice_pool' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Create business-specific account number
     */
    public function createBusinessAccount(Business $business, array $data): AccountNumber
    {
        return AccountNumber::create([
            'account_number' => $data['account_number'],
            'account_name' => $data['account_name'],
            'bank_name' => $data['bank_name'],
            'business_id' => $business->id,
            'is_pool' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Get available pool accounts count
     */
    public function getAvailablePoolCount(): int
    {
        return AccountNumber::pool()->active()->count();
    }

    /**
     * Assign account number from invoice pool
     * Similar to assignAccountNumber but uses invoice pool instead
     */
    public function assignInvoiceAccountNumber(?Business $business = null): ?AccountNumber
    {
        $startTime = microtime(true);

        // Get invoice pool accounts from cache
        $invoicePoolAccounts = Cache::remember(self::CACHE_KEY_INVOICE_POOL_ACCOUNTS, self::CACHE_TTL, function () {
            return AccountNumber::invoicePool()
                ->orderBy('id')
                ->get();
        });

        if ($invoicePoolAccounts->isEmpty()) {
            Log::warning('No available invoice pool account number found');
            return null;
        }

        // Get pending invoice account numbers (payments with invoice service)
        $pendingInvoiceAccountNumbers = $this->getPendingInvoiceAccountNumbers();
        
        // Get last used invoice account number
        $lastUsedInvoiceAccountNumber = $this->getLastUsedInvoiceAccountNumber();
        $lastUsedInvoiceAccountId = null;
        
        // Create lookup arrays
        $invoicePoolAccountsById = [];
        $invoicePoolAccountsByNumber = [];
        foreach ($invoicePoolAccounts as $account) {
            $invoicePoolAccountsById[$account->id] = $account;
            $invoicePoolAccountsByNumber[$account->account_number] = $account;
        }
        
        if ($lastUsedInvoiceAccountNumber) {
            $lastUsedAccount = $invoicePoolAccountsByNumber[$lastUsedInvoiceAccountNumber] ?? null;
            if ($lastUsedAccount) {
                $lastUsedInvoiceAccountId = $lastUsedAccount->id;
            }
        }

        $invoicePoolAccountsArray = $invoicePoolAccounts->values()->all();
        $poolCount = count($invoicePoolAccountsArray);
        
        // Find starting point: next account after last used (by ID)
        $startIndex = 0;
        if ($lastUsedInvoiceAccountId && isset($invoicePoolAccountsById[$lastUsedInvoiceAccountId])) {
            $lastUsedIndex = array_search($lastUsedInvoiceAccountId, array_column($invoicePoolAccountsArray, 'id'));
            
            if ($lastUsedIndex !== false) {
                $startIndex = $lastUsedIndex + 1;
            }
        }

        // Use array key lookup for pending check
        $pendingInvoiceAccountNumbersSet = array_flip($pendingInvoiceAccountNumbers);
        
        // Try to find the next available account
        $selectedAccount = null;
        
        // First pass: Try to find account without pending payments
        for ($i = 0; $i < $poolCount; $i++) {
            $index = ($startIndex + $i) % $poolCount;
            $account = $invoicePoolAccountsArray[$index];
            
            // Skip if this is the last used account
            if ($lastUsedInvoiceAccountNumber && $account->account_number === $lastUsedInvoiceAccountNumber) {
                continue;
            }
            
            // Skip if account has pending payments
            if (isset($pendingInvoiceAccountNumbersSet[$account->account_number])) {
                continue;
            }
            
            // Found an available account
            $selectedAccount = $account;
            break;
        }

        // Second pass: If no account found without pending, wrap around
        if (!$selectedAccount) {
            if ($lastUsedInvoiceAccountId) {
                $lastUsedIndex = array_search($lastUsedInvoiceAccountId, array_column($invoicePoolAccountsArray, 'id'));
                
                if ($lastUsedIndex !== false) {
                    $nextIndex = ($lastUsedIndex + 1) % $poolCount;
                    $selectedAccount = $invoicePoolAccountsArray[$nextIndex];
                } else {
                    $selectedAccount = $invoicePoolAccountsArray[0];
                }
            } else {
                $selectedAccount = $invoicePoolAccountsArray[0];
            }
        }

        $duration = (microtime(true) - $startTime) * 1000;
        Log::info('Assigned invoice pool account number', [
            'business_id' => $business?->id,
            'account_number' => $selectedAccount->account_number,
            'account_id' => $selectedAccount->id,
            'pool_size' => $invoicePoolAccounts->count(),
            'pending_accounts_count' => count($pendingInvoiceAccountNumbers),
            'last_used_account' => $lastUsedInvoiceAccountNumber,
            'duration_ms' => round($duration, 2),
        ]);

        return $selectedAccount;
    }

    /**
     * Get pending invoice account numbers (cached)
     */
    protected function getPendingInvoiceAccountNumbers(): array
    {
        return Cache::remember(
            self::CACHE_KEY_INVOICE_POOL_ACCOUNTS . ':pending',
            self::CACHE_TTL,
            function () {
                if (!class_exists(Payment::class)) {
                    return [];
                }
                
                return Payment::where('status', Payment::STATUS_PENDING)
                    ->whereNotNull('account_number')
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })
                    ->where(function ($query) {
                        // Check if payment is for invoice (has invoice_id in email_data or service is 'invoice')
                        $query->whereJsonContains('email_data->service', 'invoice')
                            ->orWhereNotNull('email_data->invoice_id');
                    })
                    ->pluck('account_number')
                    ->unique()
                    ->toArray();
            }
        );
    }

    /**
     * Get last used invoice account number (cached)
     */
    protected function getLastUsedInvoiceAccountNumber(): ?string
    {
        return Cache::remember(
            self::CACHE_KEY_LAST_USED_INVOICE_ACCOUNT,
            self::CACHE_TTL,
            function () {
                if (!class_exists(Payment::class)) {
                    return null;
                }
                
                $lastPayment = Payment::where('status', Payment::STATUS_PENDING)
                    ->whereNotNull('account_number')
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })
                    ->where(function ($query) {
                        $query->whereJsonContains('email_data->service', 'invoice')
                            ->orWhereNotNull('email_data->invoice_id');
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                return $lastPayment ? $lastPayment->account_number : null;
            }
        );
    }

    /**
     * Invalidate cache for invoice pool accounts
     */
    public function invalidateInvoicePoolCache(): void
    {
        Cache::forget(self::CACHE_KEY_INVOICE_POOL_ACCOUNTS);
        Cache::forget(self::CACHE_KEY_LAST_USED_INVOICE_ACCOUNT);
        Cache::forget(self::CACHE_KEY_INVOICE_POOL_ACCOUNTS . ':pending');
    }

    /**
     * Assign membership pool account number to payment request
     * Similar to invoice pool but for membership payments
     */
    public function assignMembershipAccountNumber(?Business $business = null): ?AccountNumber
    {
        $startTime = microtime(true);

        // Get membership pool accounts from cache
        $membershipPoolAccounts = Cache::remember(self::CACHE_KEY_MEMBERSHIP_POOL_ACCOUNTS, self::CACHE_TTL, function () {
            return AccountNumber::membershipPool()
                ->orderBy('id')
                ->get();
        });

        if ($membershipPoolAccounts->isEmpty()) {
            Log::warning('No available membership pool account number found');
            return null;
        }

        // Get pending membership account numbers
        $pendingMembershipAccountNumbers = $this->getPendingMembershipAccountNumbers();
        
        // Get last used membership account number
        $lastUsedMembershipAccountNumber = $this->getLastUsedMembershipAccountNumber();
        $lastUsedMembershipAccountId = null;
        
        // Create lookup arrays
        $membershipPoolAccountsById = [];
        $membershipPoolAccountsByNumber = [];
        foreach ($membershipPoolAccounts as $account) {
            $membershipPoolAccountsById[$account->id] = $account;
            $membershipPoolAccountsByNumber[$account->account_number] = $account;
        }
        
        if ($lastUsedMembershipAccountNumber) {
            $lastUsedAccount = $membershipPoolAccountsByNumber[$lastUsedMembershipAccountNumber] ?? null;
            if ($lastUsedAccount) {
                $lastUsedMembershipAccountId = $lastUsedAccount->id;
            }
        }

        $membershipPoolAccountsArray = $membershipPoolAccounts->values()->all();
        $poolCount = count($membershipPoolAccountsArray);
        
        // Find starting point: next account after last used (by ID)
        $startIndex = 0;
        if ($lastUsedMembershipAccountId && isset($membershipPoolAccountsById[$lastUsedMembershipAccountId])) {
            $lastUsedIndex = array_search($lastUsedMembershipAccountId, array_column($membershipPoolAccountsArray, 'id'));
            
            if ($lastUsedIndex !== false) {
                $startIndex = $lastUsedIndex + 1;
            }
        }

        // Use array key lookup for pending check
        $pendingMembershipAccountNumbersSet = array_flip($pendingMembershipAccountNumbers);
        
        // Try to find the next available account
        $selectedAccount = null;
        
        // First pass: Try to find account without pending payments
        for ($i = 0; $i < $poolCount; $i++) {
            $index = ($startIndex + $i) % $poolCount;
            $account = $membershipPoolAccountsArray[$index];
            
            if (!isset($pendingMembershipAccountNumbersSet[$account->account_number])) {
                $selectedAccount = $account;
                break;
            }
        }
        
        // Second pass: If all accounts have pending payments, use the next one sequentially
        if (!$selectedAccount) {
            $selectedAccount = $membershipPoolAccountsArray[$startIndex % $poolCount] ?? $membershipPoolAccountsArray[0];
        }

        // Update cache with last used account
        Cache::put(self::CACHE_KEY_LAST_USED_MEMBERSHIP_ACCOUNT, $selectedAccount->account_number, self::CACHE_TTL);

        $duration = (microtime(true) - $startTime) * 1000;
        Log::info('Assigned membership pool account number', [
            'business_id' => $business?->id,
            'account_number' => $selectedAccount->account_number,
            'account_id' => $selectedAccount->id,
            'pool_size' => $membershipPoolAccounts->count(),
            'pending_accounts_count' => count($pendingMembershipAccountNumbers),
            'last_used_account' => $lastUsedMembershipAccountNumber,
            'duration_ms' => round($duration, 2),
        ]);

        return $selectedAccount;
    }

    /**
     * Get pending membership account numbers (cached)
     */
    protected function getPendingMembershipAccountNumbers(): array
    {
        return Cache::remember(
            self::CACHE_KEY_MEMBERSHIP_POOL_ACCOUNTS . ':pending',
            self::CACHE_TTL,
            function () {
                if (!class_exists(Payment::class)) {
                    return [];
                }
                
                return Payment::where('status', Payment::STATUS_PENDING)
                    ->whereNotNull('account_number')
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })
                    ->where(function ($query) {
                        // Check if payment is for membership (has membership_id in email_data or service is 'membership')
                        $query->whereJsonContains('email_data->service', 'membership')
                            ->orWhereNotNull('email_data->membership_id');
                    })
                    ->pluck('account_number')
                    ->unique()
                    ->toArray();
            }
        );
    }

    /**
     * Get last used membership account number (cached)
     */
    protected function getLastUsedMembershipAccountNumber(): ?string
    {
        return Cache::remember(
            self::CACHE_KEY_LAST_USED_MEMBERSHIP_ACCOUNT,
            self::CACHE_TTL,
            function () {
                if (!class_exists(Payment::class)) {
                    return null;
                }
                
                $lastPayment = Payment::where('status', Payment::STATUS_PENDING)
                    ->whereNotNull('account_number')
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })
                    ->where(function ($query) {
                        $query->whereJsonContains('email_data->service', 'membership')
                            ->orWhereNotNull('email_data->membership_id');
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                return $lastPayment ? $lastPayment->account_number : null;
            }
        );
    }

    /**
     * Invalidate cache for membership pool accounts
     */
    public function invalidateMembershipPoolCache(): void
    {
        Cache::forget(self::CACHE_KEY_MEMBERSHIP_POOL_ACCOUNTS);
        Cache::forget(self::CACHE_KEY_LAST_USED_MEMBERSHIP_ACCOUNT);
        Cache::forget(self::CACHE_KEY_MEMBERSHIP_POOL_ACCOUNTS . ':pending');
    }

    /**
     * Invalidate cache for tickets pool accounts
     */
    public function invalidateTicketsPoolCache(): void
    {
        Cache::forget(self::CACHE_KEY_TICKETS_POOL_ACCOUNTS);
    }

    /**
     * Invalidate all account number caches
     */
    public function invalidateAllCaches(): void
    {
        $this->invalidatePendingAccountsCache();
        $this->invalidateInvoicePoolCache();
        $this->invalidateMembershipPoolCache();
        $this->invalidateTicketsPoolCache();
    }
}

<?php

namespace App\Services;

use App\Models\AccountNumber;
use App\Models\Business;
use App\Models\Payment;
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
     * Cache TTL in seconds (60 seconds = 1 minute)
     */
    const CACHE_TTL = 60;

    /**
     * Assign account number to payment request
     * Priority: Business-specific > Pool account
     * Pool accounts are assigned sequentially by ID, excluding last used and accounts with pending payments
     * 
     * OPTIMIZED: Uses caching to avoid querying all pending payments on every request
     */
    public function assignAccountNumber(?Business $business = null): ?AccountNumber
    {
        // NOTE: moveOrphanedAccountsToPool() moved to cron job to avoid running on every request
        // This improves performance significantly

        // Try to get business-specific account number
        if ($business && $business->hasAccountNumber()) {
            $accountNumber = $business->primaryAccountNumber();
            Log::info('Assigned business-specific account number', [
                'business_id' => $business->id,
                'account_number' => $accountNumber->account_number,
            ]);
            return $accountNumber;
        }

        // Get all pool accounts ordered by ID (sequential)
        // OPTIMIZED: Cache this if pool is very large (future optimization)
        $poolAccounts = AccountNumber::pool()
            ->active()
            ->orderBy('id')
            ->get();

        if ($poolAccounts->isEmpty()) {
            Log::warning('No available pool account number found');
            return null;
        }

        // OPTIMIZED: Use cached pending account numbers instead of querying every time
        $pendingAccountNumbers = $this->getPendingAccountNumbers();
        
        // OPTIMIZED: Use cached last used account instead of querying every time
        $lastUsedAccountNumber = $this->getLastUsedAccountNumber();
        $lastUsedAccountId = null;
        
        // OPTIMIZED: Use array key mapping for O(1) lookups instead of O(n) firstWhere()
        $poolAccountsById = [];
        $poolAccountsByNumber = [];
        foreach ($poolAccounts as $account) {
            $poolAccountsById[$account->id] = $account;
            $poolAccountsByNumber[$account->account_number] = $account;
        }
        
        if ($lastUsedAccountNumber) {
            // O(1) lookup instead of O(n) firstWhere()
            $lastUsedAccount = $poolAccountsByNumber[$lastUsedAccountNumber] ?? null;
            if ($lastUsedAccount) {
                $lastUsedAccountId = $lastUsedAccount->id;
            }
        }

        // OPTIMIZED: Use array key mapping for O(1) index lookup instead of O(n) search()
        $poolAccountsArray = $poolAccounts->values()->all();
        $poolCount = count($poolAccountsArray);
        
        // Find starting point: next account after last used (by ID)
        $startIndex = 0;
        if ($lastUsedAccountId && isset($poolAccountsById[$lastUsedAccountId])) {
            // Find index using array_search for O(n) but only once, then use array keys
            $lastUsedIndex = array_search($lastUsedAccountId, array_column($poolAccountsArray, 'id'));
            
            if ($lastUsedIndex !== false) {
                // Start from the next account after the last used one
                $startIndex = $lastUsedIndex + 1;
            }
        }

        // OPTIMIZED: Use array key lookup for pending check (O(1) instead of in_array O(n))
        $pendingAccountNumbersSet = array_flip($pendingAccountNumbers);
        
        // Try to find the next available account starting from startIndex
        // We will always assign an account - wrap around and reuse accounts with pending payments if needed
        $selectedAccount = null;
        
        // First pass: Try to find account without pending payments (excluding last used)
        for ($i = 0; $i < $poolCount; $i++) {
            $index = ($startIndex + $i) % $poolCount;
            $account = $poolAccountsArray[$index];
            
            // Skip if this is the last used account
            if ($lastUsedAccountNumber && $account->account_number === $lastUsedAccountNumber) {
                continue;
            }
            
            // OPTIMIZED: O(1) lookup instead of O(n) in_array
            if (isset($pendingAccountNumbersSet[$account->account_number])) {
                continue;
            }
            
            // Found an available account without pending payments
            $selectedAccount = $account;
            break;
        }

        // Second pass: If no account found without pending, wrap around and use next one after last used
        // This ensures we ALWAYS assign an account number when requested, even if all have pending payments
        // When we reach the end of the pool, we start again from the beginning (wraps around)
        if (!$selectedAccount) {
            if ($lastUsedAccountId) {
                $lastUsedIndex = array_search($lastUsedAccountId, array_column($poolAccountsArray, 'id'));
                
                if ($lastUsedIndex !== false) {
                    // Get next account after last used, wrapping around to beginning if at end
                    // This ensures we always have an account to assign, even if it has pending payments
                    $nextIndex = ($lastUsedIndex + 1) % $poolCount;
                    $selectedAccount = $poolAccountsArray[$nextIndex];
                } else {
                    // Last used account not found in pool, start from beginning
                    $selectedAccount = $poolAccountsArray[0];
                }
            } else {
                // No last used account, start from beginning
                $selectedAccount = $poolAccountsArray[0];
            }
        }
        
        // At this point, $selectedAccount is guaranteed to be set
        // We always assign an account number, wrapping around if needed

        Log::info('Assigned pool account number (sequentially)', [
            'business_id' => $business?->id,
            'account_number' => $selectedAccount->account_number,
            'account_id' => $selectedAccount->id,
            'pool_size' => $poolAccounts->count(),
            'pending_accounts_count' => count($pendingAccountNumbers),
            'last_used_account' => $lastUsedAccountNumber,
            'last_used_account_id' => $lastUsedAccountId,
            'start_index' => $startIndex,
        ]);

        return $selectedAccount;
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
}

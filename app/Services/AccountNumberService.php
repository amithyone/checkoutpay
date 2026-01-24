<?php

namespace App\Services;

use App\Models\AccountNumber;
use App\Models\Business;
use Illuminate\Support\Facades\Log;

class AccountNumberService
{
    /**
     * Assign account number to payment request
     * Priority: Business-specific > Pool account
     * Pool accounts are assigned sequentially by ID, excluding last used and accounts with pending payments
     */
    public function assignAccountNumber(?Business $business = null): ?AccountNumber
    {
        // First, ensure any account numbers that should be in pool are moved there
        $this->moveOrphanedAccountsToPool();

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
        $poolAccounts = AccountNumber::pool()
            ->active()
            ->orderBy('id')
            ->get();

        if ($poolAccounts->isEmpty()) {
            Log::warning('No available pool account number found');
            return null;
        }

        // Get account numbers that have pending payments (exclude these)
        $pendingAccountNumbers = [];
        if (class_exists(\App\Models\Payment::class)) {
            $pendingAccountNumbers = \App\Models\Payment::where('status', \App\Models\Payment::STATUS_PENDING)
                ->whereNotNull('account_number')
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->pluck('account_number')
                ->unique()
                ->toArray();
        }

        // Get the last used account number (from most recent pending payment)
        $lastUsedAccountNumber = null;
        $lastUsedAccountId = null;
        if (class_exists(\App\Models\Payment::class)) {
            $lastPayment = \App\Models\Payment::where('status', \App\Models\Payment::STATUS_PENDING)
                ->whereNotNull('account_number')
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($lastPayment) {
                $lastUsedAccountNumber = $lastPayment->account_number;
                // Find the account ID for the last used account
                $lastUsedAccount = $poolAccounts->firstWhere('account_number', $lastUsedAccountNumber);
                if ($lastUsedAccount) {
                    $lastUsedAccountId = $lastUsedAccount->id;
                }
            }
        }

        // Find starting point: next account after last used (by ID)
        $startIndex = 0;
        if ($lastUsedAccountId) {
            // Find the index of the last used account in the ordered pool
            $lastUsedIndex = $poolAccounts->search(function ($account) use ($lastUsedAccountId) {
                return $account->id === $lastUsedAccountId;
            });
            
            if ($lastUsedIndex !== false) {
                // Start from the next account after the last used one
                $startIndex = $lastUsedIndex + 1;
            }
        }

        // Try to find the next available account starting from startIndex
        // We will always assign an account - wrap around and reuse accounts with pending payments if needed
        $selectedAccount = null;
        $poolAccountsArray = $poolAccounts->values()->all();
        $poolCount = count($poolAccountsArray);
        
        // First pass: Try to find account without pending payments (excluding last used)
        for ($i = 0; $i < $poolCount; $i++) {
            $index = ($startIndex + $i) % $poolCount;
            $account = $poolAccountsArray[$index];
            
            // Skip if this is the last used account
            if ($lastUsedAccountNumber && $account->account_number === $lastUsedAccountNumber) {
                continue;
            }
            
            // Skip if this account has pending payments (prefer accounts without pending)
            if (in_array($account->account_number, $pendingAccountNumbers)) {
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
                $lastUsedIndex = $poolAccounts->search(function ($account) use ($lastUsedAccountId) {
                    return $account->id === $lastUsedAccountId;
                });
                
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

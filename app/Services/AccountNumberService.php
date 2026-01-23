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
     * Pool accounts are randomly selected
     */
    public function assignAccountNumber(?Business $business = null): ?AccountNumber
    {
        // First, ensure any account numbers that should be in pool are moved there
        $this->moveOrphanedAccountsToPool();

        // First, try to get business-specific account number
        if ($business && $business->hasAccountNumber()) {
            $accountNumber = $business->primaryAccountNumber();
            Log::info('Assigned business-specific account number', [
                'business_id' => $business->id,
                'account_number' => $accountNumber->account_number,
            ]);
            return $accountNumber;
        }

        // If no business-specific account, get randomly from pool
        $poolAccounts = AccountNumber::pool()
            ->active()
            ->get();

        if ($poolAccounts->isEmpty()) {
            Log::warning('No available pool account number found');
            return null;
        }

        // Randomly select from pool
        $poolAccount = $poolAccounts->random();

        Log::info('Assigned pool account number (randomly selected)', [
            'business_id' => $business?->id,
            'account_number' => $poolAccount->account_number,
            'pool_size' => $poolAccounts->count(),
        ]);
        return $poolAccount;
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

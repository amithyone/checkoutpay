<?php

namespace App\Services;

use App\Models\AccountNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AccountNumberService
{
    const CACHE_KEY_PENDING_ACCOUNTS = 'account_number_service:pending_accounts';
    const CACHE_KEY_LAST_USED_ACCOUNT = 'account_number_service:last_used_account';
    const CACHE_KEY_POOL_ACCOUNTS = 'account_number_service:pool_accounts';
    const CACHE_TTL = 300; // 5 minutes

    /**
     * Assign an account number for a payment
     * Uses pool accounts for ticket orders
     */
    public function assignAccountNumber(?string $payerName, ?int $businessId): AccountNumber
    {
        // Try to get business-specific account first
        if ($businessId) {
            $businessAccount = AccountNumber::where('business_id', $businessId)
                ->where('is_active', true)
                ->where('is_pool', false)
                ->first();

            if ($businessAccount) {
                return $businessAccount;
            }
        }

        // Use pool account
        $poolAccount = $this->getPoolAccount();

        if (!$poolAccount) {
            throw new \Exception('No available account numbers. Please contact support.');
        }

        return $poolAccount;
    }

    /**
     * Get a pool account (round-robin)
     */
    protected function getPoolAccount(): ?AccountNumber
    {
        // Get cached pool accounts
        $poolAccounts = Cache::remember(self::CACHE_KEY_POOL_ACCOUNTS, self::CACHE_TTL, function () {
            return AccountNumber::pool()
                ->active()
                ->orderBy('id')
                ->get();
        });

        if ($poolAccounts->isEmpty()) {
            return null;
        }

        // Get last used account from cache
        $lastUsedId = Cache::get(self::CACHE_KEY_LAST_USED_ACCOUNT);

        // Find next account (round-robin)
        if ($lastUsedId) {
            $lastIndex = $poolAccounts->search(function ($account) use ($lastUsedId) {
                return $account->id == $lastUsedId;
            });

            if ($lastIndex !== false && $lastIndex < $poolAccounts->count() - 1) {
                $nextAccount = $poolAccounts[$lastIndex + 1];
            } else {
                $nextAccount = $poolAccounts->first();
            }
        } else {
            $nextAccount = $poolAccounts->first();
        }

        // Cache last used
        Cache::put(self::CACHE_KEY_LAST_USED_ACCOUNT, $nextAccount->id, self::CACHE_TTL);

        return $nextAccount;
    }
}

<?php

namespace App\Services\Consumer;

use App\Models\Business;
use App\Models\WhatsappWallet;

final class ConsumerBusinessWalletLedgerService
{
    /** Keep wallet.business_balance aligned with linked merchant account for admin UI. */
    public function refreshLinkedBalanceCache(WhatsappWallet $wallet): void
    {
        if (! $wallet->linked_business_id) {
            return;
        }

        $business = $this->linkedBusiness($wallet);
        if ($business === null) {
            return;
        }

        $merchantBal = round((float) $business->balance, 2);
        if ((float) $wallet->business_balance === $merchantBal) {
            return;
        }

        $wallet->forceFill(['business_balance' => $merchantBal])->saveQuietly();
    }

    /** Balance shown in the app and used for debit checks. */
    public function resolvedBalance(WhatsappWallet $wallet): float
    {
        $business = $this->linkedBusiness($wallet);
        if ($business !== null) {
            return (float) $business->balance;
        }

        return (float) $wallet->business_balance;
    }

    public function usesLinkedMerchantBalance(WhatsappWallet $wallet): bool
    {
        return $wallet->linked_business_id !== null;
    }

    /**
     * Debit business ledger. Caller must already hold a wallet row lock inside DB::transaction().
     *
     * @return array{ok: bool, message?: string, balance_after?: float}
     */
    public function debitLockedWallet(WhatsappWallet $wallet, float $amount): array
    {
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Invalid amount.'];
        }

        if ($this->usesLinkedMerchantBalance($wallet)) {
            return $this->debitLinkedBusinessLocked($wallet, $amount);
        }

        return $this->debitWalletLedgerLocked($wallet, $amount);
    }

    /**
     * Credit business ledger. Caller must already hold a wallet row lock inside DB::transaction().
     *
     * @return array{ok: bool, balance_after: float}
     */
    public function creditLockedWallet(WhatsappWallet $wallet, float $amount): array
    {
        if ($this->usesLinkedMerchantBalance($wallet)) {
            return $this->creditLinkedBusinessLocked($wallet, $amount);
        }

        return $this->creditWalletLedgerLocked($wallet, $amount);
    }

    /** Copy merchant balance onto the wallet row when linking. */
    public function syncBalanceFromLinkedBusiness(WhatsappWallet $wallet, Business $business): void
    {
        $wallet->update([
            'linked_business_id' => $business->id,
            'business_balance' => round((float) $business->balance, 2),
        ]);
    }

    /**
     * @return array{ok: bool, message?: string, balance_after?: float}
     */
    private function debitLinkedBusinessLocked(WhatsappWallet $wallet, float $amount): array
    {
        if (! $wallet->linked_business_id) {
            return ['ok' => false, 'message' => 'Business wallet is not linked yet.'];
        }

        $business = Business::query()->lockForUpdate()->find($wallet->linked_business_id);
        if (! $business) {
            return ['ok' => false, 'message' => 'Business wallet is not linked yet.'];
        }

        $current = round((float) $business->balance, 2);
        if ($current + 0.0001 < $amount) {
            return ['ok' => false, 'message' => 'Insufficient business balance.'];
        }

        $newBal = round($current - $amount, 2);
        $business->balance = $newBal;
        $business->save();

        $wallet->business_balance = $newBal;

        return ['ok' => true, 'balance_after' => $newBal];
    }

    /**
     * @return array{ok: bool, balance_after: float}
     */
    private function creditLinkedBusinessLocked(WhatsappWallet $wallet, float $amount): array
    {
        $business = Business::query()->lockForUpdate()->find($wallet->linked_business_id);
        if (! $business) {
            $newBal = round((float) $wallet->business_balance + $amount, 2);
            $wallet->business_balance = $newBal;

            return ['ok' => true, 'balance_after' => $newBal];
        }

        $newBal = round((float) $business->balance + $amount, 2);
        $business->balance = $newBal;
        $business->save();

        $wallet->business_balance = $newBal;

        return ['ok' => true, 'balance_after' => $newBal];
    }

    /**
     * @return array{ok: bool, message?: string, balance_after?: float}
     */
    private function debitWalletLedgerLocked(WhatsappWallet $wallet, float $amount): array
    {
        $check = $wallet->canDebitBusiness($amount);
        if (! $check['ok']) {
            return $check;
        }

        $newBal = round((float) $wallet->business_balance - $amount, 2);
        $wallet->business_balance = $newBal;

        return ['ok' => true, 'balance_after' => $newBal];
    }

    /**
     * @return array{ok: bool, balance_after: float}
     */
    private function creditWalletLedgerLocked(WhatsappWallet $wallet, float $amount): array
    {
        $newBal = round((float) $wallet->business_balance + $amount, 2);
        $wallet->business_balance = $newBal;

        return ['ok' => true, 'balance_after' => $newBal];
    }

    private function linkedBusiness(WhatsappWallet $wallet): ?Business
    {
        if (! $wallet->linked_business_id) {
            return null;
        }

        if ($wallet->relationLoaded('linkedBusiness') && $wallet->linkedBusiness) {
            return $wallet->linkedBusiness;
        }

        return Business::query()->find($wallet->linked_business_id);
    }
}

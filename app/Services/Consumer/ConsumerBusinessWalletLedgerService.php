<?php

namespace App\Services\Consumer;

use App\Models\Business;
use App\Models\Payment;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;

final class ConsumerBusinessWalletLedgerService
{
    public function __construct(
        private ConsumerBusinessNameRegistrationService $businessNameRegistration,
    ) {}

    /**
     * Business receive bank details for the app Receive Funds screen.
     * Prefers BNR wallet VA, then linked CheckoutPay merchant Rubies VA.
     *
     * @return array<string, mixed>|null
     */
    public function resolveBusinessPayInPayload(WhatsappWallet $wallet): ?array
    {
        $bnr = $this->businessNameRegistration->businessPayInPayload($wallet);
        if ($bnr !== null) {
            return $bnr;
        }

        return $this->linkedMerchantPayInPayload($wallet);
    }

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
     * Record a linked merchant Rubies VA deposit on the wallet business ledger for app history.
     *
     * @param  array{sender?: string, bank_name?: string}  $webhookMeta
     */
    public function recordLinkedMerchantRubiesDeposit(
        Business $business,
        Payment $payment,
        float $grossAmount,
        array $webhookMeta = [],
    ): void {
        $wallet = WhatsappWallet::query()
            ->where('linked_business_id', $business->id)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();

        if (! $wallet) {
            return;
        }

        $paymentId = (int) $payment->id;
        if ($paymentId > 0 && WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('ledger_scope', ConsumerWalletTransactionScope::SCOPE_BUSINESS)
            ->where('type', WhatsappWalletTransaction::TYPE_BUSINESS_RUBIES_IN)
            ->where('meta->payment_id', $paymentId)
            ->exists()) {
            return;
        }

        $business->refresh();
        $this->refreshLinkedBalanceCache($wallet->fresh());

        $creditAmount = round((float) ($payment->business_receives ?? $grossAmount), 2);
        if ($creditAmount <= 0) {
            $creditAmount = round($grossAmount, 2);
        }

        $sender = trim((string) ($webhookMeta['sender'] ?? ''));
        if ($sender === '') {
            $sender = trim((string) ($payment->payer_name ?? ''));
        }
        $bank = trim((string) ($webhookMeta['bank_name'] ?? ''));
        if ($bank === '') {
            $bank = trim((string) ($payment->bank ?? ''));
        }

        WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'sender_name' => $wallet->normalizedSenderName(),
            'type' => WhatsappWalletTransaction::TYPE_BUSINESS_RUBIES_IN,
            'ledger_scope' => ConsumerWalletTransactionScope::SCOPE_BUSINESS,
            'amount' => $creditAmount,
            'balance_after' => round((float) $business->balance, 2),
            'counterparty_account_name' => $sender !== '' ? $sender : null,
            'external_reference' => trim((string) ($payment->external_reference ?? '')) !== ''
                ? (string) $payment->external_reference
                : null,
            'meta' => array_filter([
                'payment_id' => $paymentId > 0 ? $paymentId : null,
                'business_id' => (int) $business->id,
                'source' => 'linked_merchant_rubies_va',
                'gross_amount' => round($grossAmount, 2),
                'business_receives' => $creditAmount,
                'payer_name' => $sender !== '' ? $sender : null,
                'bank_name' => $bank !== '' ? $bank : null,
                'account_number' => trim((string) ($payment->account_number ?? '')) !== ''
                    ? (string) $payment->account_number
                    : null,
            ], static fn ($v) => $v !== null && $v !== ''),
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

    /**
     * @return array<string, mixed>|null
     */
    private function linkedMerchantPayInPayload(WhatsappWallet $wallet): ?array
    {
        $business = $this->linkedBusiness($wallet);
        if ($business === null) {
            return null;
        }

        $acct = trim((string) $business->rubies_business_account_number);
        if ($acct === '') {
            return null;
        }

        $accountName = trim((string) ($business->rubies_business_account_name ?? ''));
        if ($accountName === '') {
            $accountName = trim((string) ($business->name ?? ''));
        }

        return [
            'kind' => 'permanent',
            'account_number' => $acct,
            'account_name' => $accountName !== '' ? $accountName : null,
            'bank_name' => trim((string) ($business->rubies_business_bank_name ?? '')) !== ''
                ? (string) $business->rubies_business_bank_name
                : null,
            'bank_code' => trim((string) ($business->rubies_business_bank_code ?? '')) !== ''
                ? (string) $business->rubies_business_bank_code
                : null,
            'expires_at' => null,
        ];
    }
}

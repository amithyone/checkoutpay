<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\BankAccountPrefixRule;
use App\Models\WhatsappWalletTransaction;
use App\Services\MavonPayTransferService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Grow bank suggestion prefixes from successful outbound transfers.
 * First N digits of the destination NUBAN → that bank (if not already mapped).
 */
final class BankAccountPrefixLearner
{
    public function learnFromSuccessfulBankTransfer(
        string $accountNumber,
        string $bankCode,
        ?string $bankName = null,
    ): bool {
        if (! (bool) config('bank_account_prefixes.learn_from_transfers', true)) {
            return false;
        }

        if (! Schema::hasTable('bank_account_prefix_rules')) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $accountNumber) ?? '';
        $len = max(2, min(6, (int) config('bank_account_prefixes.learned_prefix_length', 4)));
        if (strlen($digits) < max(10, $len)) {
            return false;
        }

        $prefix = substr($digits, 0, $len);
        $nip = NigerianBankCodeNormalizer::toNipTransferCode(trim($bankCode));
        if ($nip === '' || strlen($prefix) < 2) {
            return false;
        }

        $exists = BankAccountPrefixRule::query()
            ->where('prefix', $prefix)
            ->where('bank_code', $nip)
            ->exists();
        if ($exists) {
            return false;
        }

        $name = trim((string) $bankName);
        if ($name === '') {
            $bank = Bank::query()->where('code', $nip)->first(['name']);
            $name = $bank ? (string) $bank->name : '';
        }

        try {
            BankAccountPrefixRule::query()->create([
                'prefix' => $prefix,
                'bank_code' => $nip,
                'bank_name' => $name !== '' ? $name : null,
                'is_active' => true,
                'notes' => 'Auto-learned from successful transfer',
                'created_by_admin_id' => null,
            ]);
        } catch (\Throwable $e) {
            // Unique race: another request learned the same pair.
            Log::debug('bank_prefix.learn_skipped', [
                'prefix' => $prefix,
                'bank_code' => $nip,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        Log::info('bank_prefix.learned', [
            'prefix' => $prefix,
            'bank_code' => $nip,
            'bank_name' => $name,
        ]);

        return true;
    }

    public function learnFromTransaction(WhatsappWalletTransaction $transaction): bool
    {
        if ($transaction->type !== WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT) {
            return false;
        }

        if ($transaction->payoutBucketLabel() !== MavonPayTransferService::BUCKET_SUCCESSFUL) {
            return false;
        }

        $meta = is_array($transaction->meta) ? $transaction->meta : [];
        if (! empty($meta['prefix_learned'])) {
            return false;
        }
        if (! empty($meta['payout_failed']) || ! empty($meta['reversed_at'])) {
            return false;
        }
        // Internal / ledger-only paths are not live bank rails — skip.
        if (($meta['payout_mode'] ?? '') === 'ledger_only' || ! empty($meta['internal_va'])) {
            return false;
        }

        $account = preg_replace('/\D+/', '', (string) ($transaction->counterparty_account_number ?? '')) ?? '';
        $bankCode = trim((string) ($transaction->counterparty_bank_code ?? ''));
        $bankName = trim((string) ($meta['bank_name'] ?? ''));

        $learned = $this->learnFromSuccessfulBankTransfer($account, $bankCode, $bankName !== '' ? $bankName : null);
        if ($learned) {
            $meta['prefix_learned'] = true;
            $meta['prefix_learned_at'] = now()->toIso8601String();
            $transaction->update(['meta' => $meta]);
        }

        return $learned;
    }

    /**
     * Fire-and-forget after the HTTP response so transfers stay fast.
     */
    public function learnLaterFromSuccessfulBankTransfer(
        string $accountNumber,
        string $bankCode,
        ?string $bankName = null,
    ): void {
        if (! (bool) config('bank_account_prefixes.learn_from_transfers', true)) {
            return;
        }

        $account = $accountNumber;
        $code = $bankCode;
        $name = $bankName;

        dispatch(function () use ($account, $code, $name): void {
            try {
                app(self::class)->learnFromSuccessfulBankTransfer($account, $code, $name);
            } catch (\Throwable $e) {
                Log::debug('bank_prefix.learn_later_failed', ['error' => $e->getMessage()]);
            }
        })->afterResponse();
    }
}

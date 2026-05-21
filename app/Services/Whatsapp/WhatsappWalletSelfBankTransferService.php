<?php

namespace App\Services\Whatsapp;

use App\Models\Setting;
use App\Models\WhatsappWallet;

/**
 * Detects "send to own bank account" and quotes admin-configurable fee (deducted from transfer amount).
 */
final class WhatsappWalletSelfBankTransferService
{
    public function isEnabled(): bool
    {
        $stored = Setting::get('whatsapp_self_bank_transfer_fee_enabled');
        if ($stored !== null) {
            return (bool) $stored;
        }

        return (bool) config('whatsapp.self_bank_transfer_fee_enabled', true);
    }

    public function feePercent(): float
    {
        $stored = Setting::get('whatsapp_self_bank_transfer_fee_percent');
        if ($stored !== null && is_numeric($stored)) {
            return max(0.0, min(25.0, (float) $stored));
        }

        return max(0.0, min(25.0, (float) config('whatsapp.self_bank_transfer_fee_percent', 1.5)));
    }

    /**
     * @return list<string>
     */
    public function fintechBankCodes(): array
    {
        $codes = config('whatsapp.self_bank_transfer_fintech_bank_codes', []);

        return is_array($codes) ? array_values(array_filter(array_map('strval', $codes))) : [];
    }

    public function isSelfTransfer(
        WhatsappWallet $wallet,
        string $account10,
        string $bankCode,
        string $beneficiaryName,
        bool $beneficiaryNameFromEnquiry = false,
    ): bool {
        $acct = preg_replace('/\D/', '', $account10) ?? '';
        if (strlen($acct) !== 10) {
            return false;
        }

        $bankNorm = $this->normalizeBankCode($bankCode);
        if ($bankNorm !== '' && $this->accountMatchesWalletPhone($wallet, $acct, $bankNorm)) {
            return true;
        }

        if (! $beneficiaryNameFromEnquiry) {
            return false;
        }

        $holder = $wallet->displayName();
        if ($holder === null || trim($holder) === '') {
            return false;
        }

        $beneficiary = $this->normalizePersonName($beneficiaryName);
        $holderNorm = $this->normalizePersonName($holder);
        if ($beneficiary === '' || $holderNorm === '') {
            return false;
        }

        $minScore = (int) config('whatsapp.self_bank_transfer_name_min_score', 68);

        return WhatsappWalletCasualSendParser::scoreNameAgainstAccountName($holderNorm, $beneficiary) >= $minScore;
    }

    /**
     * @return array{
     *   is_self_transfer: bool,
     *   fee: float,
     *   payout_amount: float,
     *   fee_percent: float,
     *   ok: bool,
     *   message?: string
     * }
     */
    public function quote(
        float $amount,
        bool $isSelf,
    ): array {
        $amount = round($amount, 2);
        if ($amount < 1) {
            return [
                'is_self_transfer' => false,
                'fee' => 0.0,
                'payout_amount' => 0.0,
                'fee_percent' => 0.0,
                'ok' => false,
                'message' => 'Minimum transfer is ₦1.',
            ];
        }

        if (! $isSelf || ! $this->isEnabled()) {
            return [
                'is_self_transfer' => false,
                'fee' => 0.0,
                'payout_amount' => $amount,
                'fee_percent' => 0.0,
                'ok' => true,
            ];
        }

        $percent = $this->feePercent();
        if ($percent <= 0) {
            return [
                'is_self_transfer' => true,
                'fee' => 0.0,
                'payout_amount' => $amount,
                'fee_percent' => 0.0,
                'ok' => true,
            ];
        }

        $fee = round($amount * ($percent / 100), 2);
        $payout = round($amount - $fee, 2);
        if ($payout < 1) {
            return [
                'is_self_transfer' => true,
                'fee' => $fee,
                'payout_amount' => $payout,
                'fee_percent' => $percent,
                'ok' => false,
                'message' => 'Amount too small after the '.$this->formatPercent($percent).' fee. Recipient must receive at least ₦1.',
            ];
        }

        return [
            'is_self_transfer' => true,
            'fee' => $fee,
            'payout_amount' => $payout,
            'fee_percent' => $percent,
            'ok' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{ok: bool, ctx: array<string, mixed>, message?: string}
     */
    public function enrichTransferContext(WhatsappWallet $wallet, array $ctx): array
    {
        $amount = isset($ctx['amount']) && is_numeric($ctx['amount']) ? round((float) $ctx['amount'], 2) : 0.0;
        $acct = isset($ctx['dest_acct']) && is_string($ctx['dest_acct']) ? $ctx['dest_acct'] : '';
        $bankCode = isset($ctx['dest_bank_code']) && is_string($ctx['dest_bank_code']) ? $ctx['dest_bank_code'] : '';
        $beneficiary = isset($ctx['dest_acct_name']) && is_string($ctx['dest_acct_name']) ? trim($ctx['dest_acct_name']) : '';
        $fromEnquiry = ! empty($ctx['dest_acct_name_verified']);

        $isSelf = $this->isSelfTransfer($wallet, $acct, $bankCode, $beneficiary, $fromEnquiry);
        $quoted = $this->quote($amount, $isSelf);
        if (! ($quoted['ok'] ?? false)) {
            return [
                'ok' => false,
                'ctx' => $ctx,
                'message' => $quoted['message'] ?? 'Invalid transfer amount.',
            ];
        }

        $ctx['is_self_transfer'] = (bool) ($quoted['is_self_transfer'] ?? false);
        $ctx['self_transfer_fee'] = (float) ($quoted['fee'] ?? 0);
        $ctx['payout_amount'] = (float) ($quoted['payout_amount'] ?? $amount);
        $ctx['self_transfer_fee_percent'] = (float) ($quoted['fee_percent'] ?? 0);

        return ['ok' => true, 'ctx' => $ctx];
    }

    public function formatPercent(float $percent): string
    {
        $s = number_format($percent, 2, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');

        return $s.'%';
    }

    private function normalizeBankCode(string $code): string
    {
        $c = preg_replace('/\D/', '', $code) ?? '';

        return $c;
    }

    private function accountMatchesWalletPhone(WhatsappWallet $wallet, string $account10, string $bankCode): bool
    {
        $codes = $this->fintechBankCodes();
        $matched = false;
        foreach ($codes as $configured) {
            if ($this->normalizeBankCode($configured) === $bankCode) {
                $matched = true;
                break;
            }
        }
        if (! $matched) {
            return false;
        }

        $walletNational = $this->national10FromE164((string) $wallet->phone_e164);
        if ($walletNational === '') {
            return false;
        }

        return $account10 === $walletNational
            || $account10 === substr($walletNational, 1)
            || ('0'.$account10) === $walletNational;
    }

    private function national10FromE164(string $e164): string
    {
        $d = preg_replace('/\D/', '', $e164) ?? '';
        if (str_starts_with($d, '234') && strlen($d) >= 13) {
            return substr($d, -10);
        }
        if (str_starts_with($d, '0') && strlen($d) === 11) {
            return substr($d, 1);
        }
        if (strlen($d) === 10) {
            return $d;
        }

        return strlen($d) > 10 ? substr($d, -10) : $d;
    }

    private function normalizePersonName(string $name): string
    {
        $n = mb_strtolower(trim($name));
        $n = preg_replace('/\b(mr|mrs|ms|miss|dr|chief|alhaji|alh|engr|bar)\b\.?/iu', '', $n) ?? $n;
        $n = preg_replace('/[^a-z0-9\s]/u', ' ', $n) ?? $n;
        $n = preg_replace('/\s+/u', ' ', $n) ?? $n;

        return trim($n);
    }
}

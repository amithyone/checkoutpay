<?php

namespace App\Services\Payout;

use App\Models\WithdrawalRequest;
use App\Services\MavonPayTransferService;

/**
 * Merchant/app-facing payout copy. Provider (MevonPay) text stays on the withdrawal
 * row for admin; this maps it to safe Checkout language.
 */
class MerchantPayoutMessageSanitizer
{
    public const GENERIC_FAILURE = 'The payout could not be completed. Please check the account details and try again.';

    public const PENDING = 'The payout was submitted and is still processing. Check this withdrawal again shortly.';

    public const SUCCESS = 'Transfer completed successfully.';

    public const NATIVE_SUCCESS = 'Transfer completed successfully.';

    public const NATIVE_PENDING = 'Checkout is still processing this transfer. Please try again in a brief moment.';

    public const NATIVE_INSUFFICIENT = 'Checkout could not complete this transfer right now. Please try again in a brief moment.';

    public const NATIVE_GENERIC = 'Checkout could not complete this transfer. Please check the details and try again.';

    public function forWithdrawal(WithdrawalRequest $withdrawal): string
    {
        $status = (string) $withdrawal->payout_status;

        if ($status === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            return self::SUCCESS;
        }

        if ($status === MavonPayTransferService::BUCKET_PENDING) {
            return self::PENDING;
        }

        return $this->sanitizeFailure((string) ($withdrawal->payout_response_message ?? ''));
    }

    /**
     * Native app / WhatsApp copy. Never names MevonPay; insufficient provider funds ask the user to retry shortly.
     */
    public function forNative(?string $bucket, ?string $providerMessage): string
    {
        $status = (string) $bucket;

        if ($status === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            return self::NATIVE_SUCCESS;
        }

        if ($status === MavonPayTransferService::BUCKET_PENDING) {
            return self::NATIVE_PENDING;
        }

        $raw = strtolower(trim((string) $providerMessage));
        if ($this->isInsufficientFunds($raw)) {
            return self::NATIVE_INSUFFICIENT;
        }

        $mapped = $this->sanitizeFailure($providerMessage);
        if ($mapped === self::GENERIC_FAILURE) {
            return self::NATIVE_GENERIC;
        }

        return $this->brandAsCheckout($mapped);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function sanitizeNativeTransactionMeta(array $meta): array
    {
        $bucket = (string) ($meta['payout_bucket'] ?? '');
        $provider = (string) ($meta['payout_response_message'] ?? $meta['provider_status_response_message'] ?? '');
        $meta['payout_response_message'] = $this->forNative($bucket !== '' ? $bucket : null, $provider);

        unset(
            $meta['mevonpay'],
            $meta['payout_raw_response'],
            $meta['provider_status_response_message'],
            $meta['provider_status_raw'],
        );

        return $meta;
    }

    public function sanitizeFailure(?string $providerMessage): string
    {
        $raw = strtolower(trim((string) $providerMessage));

        if ($raw === '') {
            return self::GENERIC_FAILURE;
        }

        if (str_contains($raw, 'could not determine bank code')) {
            return 'Unable to determine the bank. Pass bank_code from GET /api/v1/banks and try again.';
        }

        if (str_contains($raw, 'instant transfer is not available')) {
            return 'Payout is temporarily unavailable. Please try again shortly.';
        }

        if ($this->isInsufficientFunds($raw)) {
            return 'The payout could not be completed right now. Please try again later.';
        }

        if ($this->matches($raw, [
            'invalid account',
            'account does not exist',
            'account not found',
            'unregistered account',
            'unknown account',
            'invalid nuban',
            'no account',
            'account number is invalid',
        ])) {
            return 'The destination account number is invalid. Check the account number and bank, then try again.';
        }

        if ($this->matches($raw, [
            'name enquiry',
            'name inquiry',
            'name mismatch',
            'beneficiary name',
            'account name',
            'does not match',
        ])) {
            return 'The account name does not match this account number. Confirm the details and try again.';
        }

        if ($this->matches($raw, ['dormant', 'inactive account', 'closed account', 'restricted', 'frozen', 'not permitted'])) {
            return 'This bank account cannot receive transfers right now.';
        }

        if ($this->matches($raw, ['duplicate', 'already processed', 'same reference'])) {
            return 'This looks like a duplicate payout. Wait a minute and check GET /withdrawals before retrying.';
        }

        if ($this->matches($raw, ['limit exceed', 'exceeds limit', 'maximum amount', 'daily limit', 'amount too'])) {
            return 'This amount is outside the allowed payout limit.';
        }

        if ($this->matches($raw, [
            'bank not available',
            'destination bank',
            'nip',
            'switch',
            'timeout',
            'timed out',
            'temporar',
            'unavailable',
            'try again later',
        ])) {
            return 'The destination bank is temporarily unavailable. Please try again shortly.';
        }

        return self::GENERIC_FAILURE;
    }

    private function isInsufficientFunds(string $raw): bool
    {
        return $this->matches($raw, [
            'insufficient fund',
            'insufficent fund',
            'insufficient balance',
            'not enough balance',
            'low balance',
            'no sufficient',
        ]);
    }

    private function brandAsCheckout(string $text): string
    {
        $replaced = preg_replace('/\b(mevon\s*pay|mavon\s*pay|mevonpay|mavonpay|mevon|rubies)\b/i', 'Checkout', $text);

        return is_string($replaced) && $replaced !== '' ? $replaced : $text;
    }

    /**
     * @param  list<string>  $needles
     */
    private function matches(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}

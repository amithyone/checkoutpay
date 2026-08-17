<?php

namespace App\Services\Payout;

use App\Models\Bank;
use App\Services\Whatsapp\WhatsappWalletNameMatcher;
use App\Services\WhatsappWalletBankPayoutService;
use App\Services\WithdrawalMavonPayPayoutService;

/**
 * Name enquiry for merchant payout API — validate before POST /withdrawal.
 */
class MerchantPayoutAccountValidationService
{
    public function __construct(
        private WhatsappWalletBankPayoutService $bankPayout,
        private WithdrawalMavonPayPayoutService $payout,
    ) {}

    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>}
     */
    public function validate(string $accountNumber, string $bankCode, ?string $bankName = null): array
    {
        $acct = preg_replace('/\D/', '', $accountNumber) ?? '';
        if (strlen($acct) !== 10) {
            return [
                'ok' => false,
                'message' => 'Account number must be 10 digits.',
            ];
        }

        $resolvedCode = $this->payout->resolveBankCode($bankCode, (string) ($bankName ?? ''));
        if (! $resolvedCode) {
            return [
                'ok' => false,
                'message' => 'Unable to determine bank code. Pass bank_code from GET /api/v1/banks.',
            ];
        }

        if (! $this->bankPayout->isNameEnquiryAvailable()) {
            return [
                'ok' => false,
                'message' => 'Account verification is temporarily unavailable. Try again shortly.',
            ];
        }

        $enquiry = $this->bankPayout->nameEnquiry($resolvedCode, $acct);
        if ($enquiry === null) {
            return [
                'ok' => false,
                'message' => 'Could not verify this account. Check the account number and bank code, then try again.',
            ];
        }

        $verifiedCode = (string) ($enquiry['bank_code'] ?? $resolvedCode);
        $verifiedName = trim((string) ($enquiry['account_name'] ?? ''));
        $resolvedBankName = $this->resolveBankName($verifiedCode, $bankName);

        return [
            'ok' => true,
            'data' => [
                'account_number' => $acct,
                'account_name' => $verifiedName,
                'bank_code' => $verifiedCode,
                'bank_name' => $resolvedBankName,
            ],
        ];
    }

    /**
     * Block payout when name enquiry is available but account/name cannot be verified.
     *
     * @return array{success: false, message: string, code?: string, data?: array<string, mixed>}|null
     */
    public function payoutPrecheckFailure(
        string $accountNumber,
        string $accountName,
        string $bankCode,
        string $bankName,
    ): ?array {
        if (! $this->bankPayout->isNameEnquiryAvailable()) {
            return null;
        }

        $resolvedCode = $this->payout->resolveBankCode($bankCode, $bankName);
        if (! $resolvedCode) {
            return [
                'success' => false,
                'message' => 'Unable to determine bank code. Pass bank_code from GET /api/v1/banks and try again.',
                'code' => 'invalid_bank_code',
            ];
        }

        $acct = preg_replace('/\D/', '', $accountNumber) ?? '';
        $enquiry = $this->bankPayout->nameEnquiry($resolvedCode, $acct);
        if ($enquiry === null) {
            return [
                'success' => false,
                'message' => 'Could not verify the destination account. Call POST /api/v1/validate-account first and use the returned account_name and bank_code.',
                'code' => 'account_not_verified',
            ];
        }

        $verifiedName = trim((string) ($enquiry['account_name'] ?? ''));
        if ($verifiedName === '' || ! WhatsappWalletNameMatcher::passes($accountName, $verifiedName)) {
            return [
                'success' => false,
                'message' => 'account_name does not match the verified bank account name. Call POST /api/v1/validate-account and send the exact account_name it returns on POST /withdrawal.',
                'code' => 'account_name_mismatch',
                'data' => [
                    'verified_account_name' => $verifiedName,
                    'verified_bank_code' => (string) ($enquiry['bank_code'] ?? $resolvedCode),
                ],
            ];
        }

        return null;
    }

    private function resolveBankName(string $bankCode, ?string $hint): string
    {
        if ($hint !== null && trim($hint) !== '') {
            return trim($hint);
        }

        $name = Bank::query()->where('code', $bankCode)->value('name');

        return is_string($name) && trim($name) !== '' ? trim($name) : '';
    }
}

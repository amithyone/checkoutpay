<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\Business;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Str;

/**
 * Runs MavonPay createtransfer for a withdrawal after it is persisted.
 * Used by rentals API, admin withdrawal create, and business dashboard withdrawal create.
 */
class WithdrawalMavonPayPayoutService
{
    public function __construct(
        protected MavonPayTransferService $mavon
    ) {}

    public function isMavonConfigured(): bool
    {
        return $this->mavon->isConfigured();
    }

    /**
     * Resolve bank code from explicit hint (e.g. from form / saved account) or banks table by name.
     */
    public function resolveBankCode(?string $hint, string $bankName): ?string
    {
        if ($hint !== null && trim($hint) !== '') {
            return trim($hint);
        }

        $bank = Bank::query()
            ->whereRaw('LOWER(name) = LOWER(?)', [$bankName])
            ->first();

        return $bank?->code;
    }

    /**
     * Attempt instant payout. Updates withdrawal payout_* fields and status; decrements business balance on success.
     */
    public function processWithdrawal(WithdrawalRequest $withdrawal, Business $business, ?string $bankCode): void
    {
        $withdrawal->update([
            'payout_provider' => MavonPayTransferService::PROVIDER,
            'payout_status' => 'not_started',
        ]);

        if (! $this->mavon->isConfigured()) {
            $withdrawal->update([
                'payout_status' => 'failed',
                'payout_response_message' => 'Instant transfer is not available right now. Your withdrawal request has been submitted for manual processing.',
                'payout_attempted_at' => now(),
                'payout_failed_at' => now(),
            ]);

            return;
        }

        $resolvedCode = $this->resolveBankCode($bankCode, $withdrawal->bank_name);
        if (! $resolvedCode) {
            $withdrawal->update([
                'payout_status' => 'failed',
                'payout_response_message' => 'Could not determine bank code for payout. Add bank_code or ensure the bank exists in the system.',
                'payout_attempted_at' => now(),
                'payout_failed_at' => now(),
            ]);

            return;
        }

        $reference = 'wd_'.$withdrawal->id.'_'.Str::lower(Str::random(10));
        $sessionId = 'WD'.$withdrawal->id.'_'.now()->format('YmdHis');

        $result = $this->mavon->createTransfer([
            'amount' => (float) $withdrawal->amount,
            'bankCode' => $resolvedCode,
            'bankName' => $withdrawal->bank_name,
            'creditAccountName' => $withdrawal->account_name,
            'creditAccountNumber' => $withdrawal->account_number,
            'narration' => 'Business withdrawal',
            'reference' => $reference,
            'sessionId' => $sessionId,
        ]);

        $bucket = $result['bucket'] ?? MavonPayTransferService::BUCKET_FAILED;

        $update = [
            'payout_reference' => $reference,
            'payout_response_code' => $result['response_code'] ?? null,
            'payout_response_message' => $result['response_message'] ?? null,
            'payout_raw_response' => $result['raw'] ?? null,
            'payout_attempted_at' => now(),
            'payout_status' => $bucket,
            'payout_failed_at' => $bucket === MavonPayTransferService::BUCKET_FAILED ? now() : null,
            'payout_succeeded_at' => $bucket === MavonPayTransferService::BUCKET_SUCCESSFUL ? now() : null,
            'status' => $bucket === MavonPayTransferService::BUCKET_SUCCESSFUL
                ? WithdrawalRequest::STATUS_PROCESSED
                : WithdrawalRequest::STATUS_PENDING,
        ];
        if ($bucket === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            $update['processed_at'] = now();
        }
        $withdrawal->update($update);

        if ($bucket === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            $business->decrement('balance', $withdrawal->amount);
        }
    }

    /**
     * Human-readable flash line after processWithdrawal (optional).
     */
    public function summaryMessage(WithdrawalRequest $withdrawal): string
    {
        if (! $this->isMavonConfigured()) {
            return 'Submitted for manual processing (instant transfer is not configured).';
        }

        $status = $withdrawal->payout_status;
        if ($status === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            return 'Transfer completed successfully via MavonPay.';
        }
        if ($status === MavonPayTransferService::BUCKET_PENDING) {
            return 'Transfer submitted; bank status is pending. Check this withdrawal for updates.';
        }

        return 'Instant transfer could not be completed: '.($withdrawal->payout_response_message ?? 'Unknown error').' You can process it manually from the admin panel.';
    }
}

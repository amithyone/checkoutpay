<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\Business;
use App\Models\MevonPayLedgerEntry;
use App\Models\WithdrawalRequest;
use App\Services\MevonPay\MevonPayLedgerRecorder;
use App\Services\MevonPay\MevonPayPayoutService;
use App\Services\Payout\BankPayoutNarration;
use App\Services\Payout\MerchantPayoutMessageSanitizer;
use Illuminate\Support\Str;

/**
 * Instant payout after a withdrawal is persisted.
 * Checkout identity uses platform /V1/createtransfer; business identity uses /V1/payout
 * from the merchant permanent Rubies VA (same as native-app send money).
 */
class WithdrawalMavonPayPayoutService
{
    public const COOLDOWN_MINUTES = 1;

    public const DEBIT_CHECKOUT = 'checkout';

    public const DEBIT_BUSINESS = 'business';

    public function __construct(
        protected MavonPayTransferService $mavon,
        protected MevonPayPayoutService $payout,
        protected MevonPayLedgerRecorder $ledger,
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
     * Whether this business's withdrawals should debit their permanent VA (recipient sees business name).
     */
    public function usesBusinessDebit(Business $business): bool
    {
        return $business->withdrawal_debit_source === self::DEBIT_BUSINESS
            && $business->hasPermanentSettlementAccount();
    }

    /**
     * @return array{source: string, debit_account_name: string, debit_account_number: string, payout_api: string}
     */
    public function debitProfile(Business $business): array
    {
        if ($this->usesBusinessDebit($business) && $this->payout->isConfigured()) {
            $name = trim((string) ($business->rubies_business_account_name ?: $business->name));
            if ($name === '') {
                $name = 'Checkout';
            }

            return [
                'source' => self::DEBIT_BUSINESS,
                'debit_account_name' => $name,
                'debit_account_number' => trim((string) $business->rubies_business_account_number),
                'payout_api' => MevonPayLedgerEntry::PAYOUT_API_PAYOUT,
            ];
        }

        $poolName = trim((string) config('services.mevonpay.debit_account_name', ''));

        return [
            'source' => self::DEBIT_CHECKOUT,
            'debit_account_name' => $poolName !== '' ? $poolName : 'Checkout',
            'debit_account_number' => trim((string) config('services.mevonpay.debit_account_number', '')),
            'payout_api' => MevonPayLedgerEntry::PAYOUT_API_CREATETRANSFER,
        ];
    }

    /**
     * Attempt instant payout. Updates withdrawal payout_* fields and status; decrements business balance on success.
     */
    public function processWithdrawal(WithdrawalRequest $withdrawal, Business $business, ?string $bankCode): void
    {
        $debit = $this->debitProfile($business);
        $usePayout = $debit['payout_api'] === MevonPayLedgerEntry::PAYOUT_API_PAYOUT;

        $withdrawal->update([
            'payout_provider' => MavonPayTransferService::PROVIDER,
            'payout_status' => 'not_started',
        ]);

        $ready = $usePayout ? $this->payout->isConfigured() : $this->mavon->isConfigured();
        if (! $ready) {
            $withdrawal->update([
                'payout_status' => 'failed',
                'payout_response_message' => 'Instant transfer is not available right now. Your withdrawal request has been submitted for manual processing.',
                'payout_attempted_at' => now(),
                'payout_failed_at' => now(),
            ]);

            return;
        }

        $resolvedCode = $this->resolveBankCode($bankCode, $withdrawal->bank_name);
        if ($resolvedCode !== null && trim($resolvedCode) !== '') {
            $resolvedCode = NigerianBankCodeNormalizer::toNipTransferCode($resolvedCode);
        }
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
        $narration = BankPayoutNarration::forBusinessWithdrawal($business, $withdrawal->bank_narration);

        if ($usePayout) {
            $result = $this->payout->createPayout([
                'amount' => (float) $withdrawal->amount,
                'bankCode' => $resolvedCode,
                'bankName' => $withdrawal->bank_name,
                'creditAccountName' => $withdrawal->account_name,
                'creditAccountNumber' => $withdrawal->account_number,
                'debitAccountNumber' => $debit['debit_account_number'],
                'debitAccountName' => $debit['debit_account_name'],
                'narration' => $narration,
                'reference' => $reference,
            ]);
        } else {
            $result = $this->mavon->createTransfer([
                'amount' => (float) $withdrawal->amount,
                'bankCode' => $resolvedCode,
                'bankName' => $withdrawal->bank_name,
                'creditAccountName' => $withdrawal->account_name,
                'creditAccountNumber' => $withdrawal->account_number,
                'narration' => $narration,
                'reference' => $reference,
                'sessionId' => $sessionId,
            ]);
        }

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

        $this->ledger->recordOutbound(
            MevonPayLedgerEntry::FLOW_BUSINESS_WITHDRAWAL,
            (float) $withdrawal->amount,
            $reference,
            $debit['payout_api'],
            $bucket,
            $debit['debit_account_number'],
            $withdrawal,
            [
                'business_id' => $business->id,
                'withdrawal_id' => $withdrawal->id,
                'narration' => $narration,
                'response_code' => $result['response_code'] ?? null,
                'debit_source' => $debit['source'],
                'debit_account_name' => $debit['debit_account_name'],
            ],
        );

        if ($bucket === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            $business->decrement('balance', $withdrawal->amount);
        }
    }

    /**
     * Admin-facing line (may include provider text stored on the row).
     */
    public function summaryMessage(WithdrawalRequest $withdrawal): string
    {
        if (! $this->isMavonConfigured() && ! $this->payout->isConfigured()) {
            return 'Submitted for manual processing (instant transfer is not configured).';
        }

        $status = $withdrawal->payout_status;
        if ($status === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            return 'Transfer completed successfully.';
        }
        if ($status === MavonPayTransferService::BUCKET_PENDING) {
            return MerchantPayoutMessageSanitizer::PENDING;
        }

        $detail = trim((string) ($withdrawal->payout_response_message ?? ''));

        return $detail !== ''
            ? 'Instant transfer could not be completed: '.$detail
            : 'Instant transfer could not be completed.';
    }

    /**
     * Merchant API / dashboard copy. Never forwards raw MevonPay errors.
     */
    public function merchantSummaryMessage(WithdrawalRequest $withdrawal): string
    {
        return app(MerchantPayoutMessageSanitizer::class)->forWithdrawal($withdrawal);
    }
}

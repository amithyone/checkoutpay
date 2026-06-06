<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\VirtualCardRequest;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\VirtualCardFeeRefundService;
use App\Services\Consumer\VirtualCardProviderResponseService;
use App\Services\MevonPay\MevonPayCardApiClient;
use App\Services\MevonPay\MevonPayUsdAutoFundService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class AdminVirtualCardService
{
    public function __construct(
        private MevonPayCardApiClient $cardApi,
        private VirtualCardFeeRefundService $refunds,
        private VirtualCardProviderResponseService $providerResponse,
        private MevonPayUsdAutoFundService $usdAutoFund,
    ) {}

    /**
     * @return array<string, int|float>
     */
    public function stats(): array
    {
        $counts = VirtualCardRequest::query()
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $feeTotal = (float) VirtualCardRequest::query()
            ->whereIn('status', [
                VirtualCardRequest::STATUS_SUBMITTED,
                VirtualCardRequest::STATUS_ACTIVE,
            ])
            ->sum('fee_ngn');

        return [
            'pending' => (int) ($counts[VirtualCardRequest::STATUS_PENDING] ?? 0),
            'submitted' => (int) ($counts[VirtualCardRequest::STATUS_SUBMITTED] ?? 0),
            'active' => (int) ($counts[VirtualCardRequest::STATUS_ACTIVE] ?? 0),
            'failed' => (int) ($counts[VirtualCardRequest::STATUS_FAILED] ?? 0),
            'total_fees_ngn' => $feeTotal,
        ];
    }

    public function indexQuery(Request $request): LengthAwarePaginator
    {
        return $this->filteredQuery($request)
            ->with('wallet')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @return array{card: VirtualCardRequest, feeTransaction: ?WhatsappWalletTransaction, canMarkActive: bool, canMarkFailed: bool, canRetry: bool, canRefund: bool}
     */
    public function showContext(VirtualCardRequest $card): array
    {
        $card->load(['wallet', 'handledBy']);
        $feeTxn = $this->feeTransaction($card);

        return [
            'card' => $card,
            'feeTransaction' => $feeTxn,
            'canMarkActive' => $this->canMarkActive($card),
            'canMarkFailed' => $this->canMarkFailed($card),
            'canRetry' => $this->canRetry($card),
            'canRefund' => $this->canRefund($card, $feeTxn),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function markActive(VirtualCardRequest $card, Admin $admin): array
    {
        if (! $this->canMarkActive($card)) {
            return ['ok' => false, 'message' => 'Only pending or submitted requests can be marked active.'];
        }

        $card->update([
            'status' => VirtualCardRequest::STATUS_ACTIVE,
            'activated_at' => now(),
            'handled_by_admin_id' => $admin->id,
            'failure_reason' => null,
        ]);

        return ['ok' => true, 'message' => 'Card marked as active.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function markFailed(VirtualCardRequest $card, Admin $admin, string $reason): array
    {
        if (! $this->canMarkFailed($card)) {
            return ['ok' => false, 'message' => 'Only pending or submitted requests can be marked failed.'];
        }

        $reason = trim($reason);
        if ($reason === '') {
            return ['ok' => false, 'message' => 'A failure reason is required.'];
        }

        $card->update([
            'status' => VirtualCardRequest::STATUS_FAILED,
            'failure_reason' => $reason,
            'handled_by_admin_id' => $admin->id,
        ]);

        return ['ok' => true, 'message' => 'Card marked as failed.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function updateNotes(VirtualCardRequest $card, string $notes): array
    {
        $card->update(['admin_notes' => trim($notes) !== '' ? trim($notes) : null]);

        return ['ok' => true, 'message' => 'Notes saved.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function retryProvider(VirtualCardRequest $card): array
    {
        if (! $this->canRetry($card)) {
            return ['ok' => false, 'message' => 'Retry is only allowed for failed or pending requests without a provider card ID.'];
        }
        if (! $this->cardApi->isConfigured()) {
            return ['ok' => false, 'message' => 'MevonPay card API is not configured.'];
        }

        $payload = $card->request_payload;
        if (! is_array($payload) || $payload === []) {
            return ['ok' => false, 'message' => 'No stored request payload to resend.'];
        }

        $feeUsd = (float) ($card->fee_usd ?? 0);
        if ($feeUsd > 0) {
            $fund = $this->usdAutoFund->ensureUsdBalance($feeUsd, 'admin_virtual_card_retry');
            if (! ($fund['ok'] ?? false)) {
                return ['ok' => false, 'message' => (string) ($fund['message'] ?? 'Could not prepare MevonPay USD balance.')];
            }
        }

        $api = $this->cardApi->createCard($payload);
        if (! ($api['ok'] ?? false) && $feeUsd > 0 && $this->usdAutoFund->isInsufficientUsdError((string) ($api['message'] ?? ''))) {
            $retryFund = $this->usdAutoFund->fundAfterProviderInsufficientUsd($feeUsd, 'admin_virtual_card_retry_2');
            if ($retryFund['ok'] ?? false) {
                $api = $this->cardApi->createCard($payload);
            }
        }

        if ($api['ok'] ?? false) {
            $this->providerResponse->applySuccess($card, $api);

            return ['ok' => true, 'message' => (string) ($api['message'] ?? 'Provider request succeeded.')];
        }

        $this->providerResponse->applyFailure($card, $api, (string) ($api['message'] ?? 'Provider error'));

        return ['ok' => false, 'message' => (string) ($api['message'] ?? 'Provider request failed.')];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function refundFee(VirtualCardRequest $card, Admin $admin): array
    {
        $feeTxn = $this->feeTransaction($card);
        if ($feeTxn && $this->refunds->isFeeRefunded($feeTxn)) {
            return ['ok' => true, 'message' => 'Fee was already refunded.', 'already_refunded' => true];
        }
        if (! $this->canRefund($card, $feeTxn)) {
            return ['ok' => false, 'message' => 'Fee cannot be refunded for this request (missing txn, already refunded, or invalid status).'];
        }

        $result = $this->refunds->refundFee(
            (int) $card->whatsapp_wallet_id,
            (string) $card->external_reference,
            (float) $card->fee_ngn,
            'Admin manual refund by '.$admin->name
        );

        if ($result['ok']) {
            $card->update(['handled_by_admin_id' => $admin->id]);
        }

        return $result;
    }

    public function canMarkActive(VirtualCardRequest $card): bool
    {
        return in_array($card->status, [
            VirtualCardRequest::STATUS_PENDING,
            VirtualCardRequest::STATUS_SUBMITTED,
        ], true);
    }

    public function canMarkFailed(VirtualCardRequest $card): bool
    {
        return in_array($card->status, [
            VirtualCardRequest::STATUS_PENDING,
            VirtualCardRequest::STATUS_SUBMITTED,
        ], true);
    }

    public function canRetry(VirtualCardRequest $card): bool
    {
        if ($card->status === VirtualCardRequest::STATUS_FAILED) {
            return true;
        }

        if ($card->status === VirtualCardRequest::STATUS_PENDING && trim((string) $card->card_external_id) === '') {
            return true;
        }

        return false;
    }

    public function canRefund(VirtualCardRequest $card, ?WhatsappWalletTransaction $feeTxn): bool
    {
        if (! $feeTxn || $this->refunds->isFeeRefunded($feeTxn)) {
            return false;
        }

        return in_array($card->status, [
            VirtualCardRequest::STATUS_FAILED,
            VirtualCardRequest::STATUS_PENDING,
            VirtualCardRequest::STATUS_SUBMITTED,
        ], true);
    }

    private function feeTransaction(VirtualCardRequest $card): ?WhatsappWalletTransaction
    {
        if (! $card->external_reference) {
            return null;
        }

        return WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $card->whatsapp_wallet_id)
            ->where('external_reference', $card->external_reference)
            ->where('type', WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE)
            ->first();
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = VirtualCardRequest::query();

        $status = $request->string('status')->toString();
        if ($status !== '' && in_array($status, [
            VirtualCardRequest::STATUS_PENDING,
            VirtualCardRequest::STATUS_SUBMITTED,
            VirtualCardRequest::STATUS_ACTIVE,
            VirtualCardRequest::STATUS_FAILED,
        ], true)) {
            $query->where('status', $status);
        }

        $q = trim($request->string('q')->toString());
        if ($q !== '') {
            $query->where(function (Builder $inner) use ($q) {
                $inner->where('external_reference', 'like', '%'.$q.'%')
                    ->orWhere('card_name', 'like', '%'.$q.'%')
                    ->orWhere('card_external_id', 'like', '%'.$q.'%')
                    ->orWhereHas('wallet', function (Builder $w) use ($q) {
                        $w->where('phone_e164', 'like', '%'.$q.'%')
                            ->orWhere('kyc_fname', 'like', '%'.$q.'%')
                            ->orWhere('kyc_lname', 'like', '%'.$q.'%');
                    });
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        return $query;
    }
}

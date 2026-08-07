<?php

namespace App\Services\Credit;

use App\Models\Business;
use App\Models\CreditFacilityRequest;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerBusinessWalletLedgerService;
use Illuminate\Support\Facades\DB;

final class CreditFacilityRequestService
{
    public function __construct(
        private ConsumerBusinessWalletLedgerService $businessLedger,
        private OverdraftEligibilityService $eligibility,
    ) {}

    /**
     * @param  array{kind: string, amount: float|int|string, note?: string|null}  $input
     * @return array{ok: bool, message: string, status?: int, request?: CreditFacilityRequest}
     */
    public function submit(WhatsappWallet $wallet, array $input): array
    {
        $kind = strtolower(trim((string) ($input['kind'] ?? '')));
        $amount = round((float) ($input['amount'] ?? 0), 2);
        $note = isset($input['note']) ? trim((string) $input['note']) : null;
        if ($note === '') {
            $note = null;
        }

        if (! in_array($kind, [CreditFacilityRequest::KIND_OVERDRAFT, CreditFacilityRequest::KIND_LOAN], true)) {
            return ['ok' => false, 'message' => 'Kind must be overdraft or loan.', 'status' => 422];
        }
        if ($amount < 1) {
            return ['ok' => false, 'message' => 'Amount must be at least ₦1.', 'status' => 422];
        }

        // Personal wallets are allowed. Linked business uses business volume; otherwise personal 90d volume.
        $business = $wallet->linked_business_id
            ? $this->businessLedger->resolveLinkedOrMatchedBusiness($wallet)
            : null;
        if ($business && ! $business->is_active) {
            $business = null;
        }

        $eligibility = $this->eligibility->computeForWallet($wallet, $business instanceof Business ? $business : null);
        if ($eligibility['tier'] === null || $eligibility['tier_max_limit'] <= 0) {
            $need = number_format($this->eligibility->tier1Threshold(), 0);

            return [
                'ok' => false,
                'message' => sprintf(
                    'Not eligible yet. Your last 90 days of %s transactions (₦%s) must reach at least ₦%s.',
                    $eligibility['source'],
                    number_format($eligibility['volume_90d'], 2),
                    $need
                ),
                'status' => 422,
            ];
        }
        if ($amount > $eligibility['tier_max_limit'] + 0.0001) {
            return [
                'ok' => false,
                'message' => sprintf(
                    'Amount exceeds your tier max of ₦%s (90d %s volume ₦%s).',
                    number_format($eligibility['tier_max_limit'], 2),
                    $eligibility['source'],
                    number_format($eligibility['volume_90d'], 2)
                ),
                'status' => 422,
            ];
        }

        if ($kind === CreditFacilityRequest::KIND_OVERDRAFT) {
            return $this->submitOverdraft($wallet, $business, $amount, $note, $eligibility);
        }

        return $this->submitLoan($wallet, $business, $amount, $note, $eligibility);
    }

    /**
     * @param  array{volume_90d: float, tier: ?string, tier_max_limit: float, source: string}  $eligibility
     * @return array{ok: bool, message: string, status?: int, request?: CreditFacilityRequest}
     */
    private function submitOverdraft(
        WhatsappWallet $wallet,
        ?Business $business,
        float $amount,
        ?string $note,
        array $eligibility,
    ): array {
        if ($business instanceof Business) {
            $this->eligibility->syncBusiness($business);
            $business = $business->fresh() ?? $business;

            if ($business->overdraft_status === 'pending') {
                return ['ok' => false, 'message' => 'You already have a pending overdraft application.', 'status' => 422];
            }
            if ($business->hasOverdraftApproved()) {
                return ['ok' => false, 'message' => 'Overdraft is already approved.', 'status' => 422];
            }
        }

        $pending = CreditFacilityRequest::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('kind', CreditFacilityRequest::KIND_OVERDRAFT)
            ->where('status', CreditFacilityRequest::STATUS_PENDING)
            ->exists();
        if ($pending) {
            return ['ok' => false, 'message' => 'You already have a pending overdraft application.', 'status' => 422];
        }

        $row = DB::transaction(function () use ($wallet, $business, $amount, $note, $eligibility) {
            $request = CreditFacilityRequest::query()->create([
                'whatsapp_wallet_id' => $wallet->id,
                'business_id' => $business?->id,
                'kind' => CreditFacilityRequest::KIND_OVERDRAFT,
                'amount' => $amount,
                'currency' => 'NGN',
                'note' => $note,
                'status' => CreditFacilityRequest::STATUS_PENDING,
                'meta' => [
                    'source' => 'consumer_app',
                    'path' => 'wallet/credit-facility/request',
                    'ledger' => $business ? 'business' : 'personal',
                    'volume_90d' => $eligibility['volume_90d'],
                    'volume_tier' => $eligibility['tier'],
                    'tier_max_limit' => $eligibility['tier_max_limit'],
                    'volume_source' => $eligibility['source'],
                ],
            ]);

            if ($business instanceof Business) {
                $business->update([
                    'overdraft_status' => 'pending',
                    'overdraft_requested_at' => now(),
                    'overdraft_requested_amount' => $amount,
                    'overdraft_repayment_mode' => $business->overdraft_repayment_mode
                        ?: OverdraftInstallmentService::MODE_SINGLE,
                    'overdraft_application_notes' => $note,
                ]);
            }

            return $request;
        });

        return [
            'ok' => true,
            'message' => 'Overdraft request submitted.',
            'request' => $row->fresh(),
        ];
    }

    /**
     * @param  array{volume_90d: float, tier: ?string, tier_max_limit: float, source: string}  $eligibility
     * @return array{ok: bool, message: string, status?: int, request?: CreditFacilityRequest}
     */
    private function submitLoan(
        WhatsappWallet $wallet,
        ?Business $business,
        float $amount,
        ?string $note,
        array $eligibility,
    ): array {
        $pending = CreditFacilityRequest::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('kind', CreditFacilityRequest::KIND_LOAN)
            ->where('status', CreditFacilityRequest::STATUS_PENDING)
            ->exists();
        if ($pending) {
            return ['ok' => false, 'message' => 'You already have a pending loan request.', 'status' => 422];
        }

        $row = CreditFacilityRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'business_id' => $business?->id,
            'kind' => CreditFacilityRequest::KIND_LOAN,
            'amount' => $amount,
            'currency' => 'NGN',
            'note' => $note,
            'status' => CreditFacilityRequest::STATUS_PENDING,
            'meta' => [
                'source' => 'consumer_app',
                'path' => 'wallet/credit-facility/request',
                'ledger' => $business ? 'business' : 'personal',
                'volume_90d' => $eligibility['volume_90d'],
                'volume_tier' => $eligibility['tier'],
                'tier_max_limit' => $eligibility['tier_max_limit'],
                'volume_source' => $eligibility['source'],
            ],
        ]);

        return [
            'ok' => true,
            'message' => 'Loan request submitted.',
            'request' => $row->fresh(),
        ];
    }
}

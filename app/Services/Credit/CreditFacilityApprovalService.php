<?php

namespace App\Services\Credit;

use App\Models\Business;
use App\Models\CreditFacilityRequest;
use App\Models\TransactionLog;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerBusinessWalletLedgerService;
use App\Services\Consumer\ConsumerWalletPushNotificationService;
use App\Services\Whatsapp\WhatsappWalletMoneyFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CreditFacilityApprovalService
{
    public function __construct(
        private OverdraftFundingService $funding,
        private OverdraftEligibilityService $eligibility,
        private ConsumerBusinessWalletLedgerService $businessLedger,
        private ConsumerWalletPushNotificationService $walletPush,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function approve(
        CreditFacilityRequest $request,
        Business $funder,
        float $approvedAmount,
        int $adminId,
        ?string $adminNotes = null,
        ?float $overdraftLimit = null,
    ): array {
        if ($request->status !== CreditFacilityRequest::STATUS_PENDING) {
            return ['ok' => false, 'message' => 'Request is not pending.'];
        }

        $approvedAmount = round(max(0, $approvedAmount), 2);
        if ($approvedAmount < 1) {
            return ['ok' => false, 'message' => 'Approved amount must be at least ₦1.'];
        }

        if ($funder->id === (int) $request->business_id) {
            return ['ok' => false, 'message' => 'Master loan account cannot fund itself.'];
        }

        $capacity = $this->funding->availableCapacityForFunder($funder);
        if ($capacity + 0.0001 < $approvedAmount) {
            return [
                'ok' => false,
                'message' => 'Master loan account has insufficient balance. Available: ₦'.number_format($capacity, 2),
            ];
        }

        if ($request->kind === CreditFacilityRequest::KIND_OVERDRAFT) {
            return $this->approveOverdraft($request, $funder, $approvedAmount, $adminId, $adminNotes, $overdraftLimit);
        }

        return $this->approveLoan($request, $funder, $approvedAmount, $adminId, $adminNotes);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function reject(CreditFacilityRequest $request, int $adminId, ?string $adminNotes = null): array
    {
        if ($request->status !== CreditFacilityRequest::STATUS_PENDING) {
            return ['ok' => false, 'message' => 'Request is not pending.'];
        }

        DB::transaction(function () use ($request, $adminId, $adminNotes) {
            $request->update([
                'status' => CreditFacilityRequest::STATUS_REJECTED,
                'admin_notes' => $adminNotes,
                'approved_by_admin_id' => $adminId,
                'approved_at' => now(),
            ]);

            if ($request->kind === CreditFacilityRequest::KIND_OVERDRAFT && $request->business_id) {
                Business::query()->whereKey($request->business_id)->where('overdraft_status', 'pending')->update([
                    'overdraft_status' => 'rejected',
                ]);
            }
        });

        return ['ok' => true, 'message' => 'Request rejected.'];
    }

    /**
     * Overdraft: set limit + link master account. Master is debited when they draw,
     * credited when they repay (see OverdraftFundingService::syncOnBalanceChange).
     *
     * @return array{ok: bool, message: string}
     */
    private function approveOverdraft(
        CreditFacilityRequest $request,
        Business $funder,
        float $approvedAmount,
        int $adminId,
        ?string $adminNotes,
        ?float $overdraftLimit,
    ): array {
        $business = $request->business_id
            ? Business::query()->find($request->business_id)
            : null;

        // Personal overdraft (no linked business): disburse from master to personal wallet now.
        if (! $business) {
            return $this->approveLoan($request, $funder, $approvedAmount, $adminId, $adminNotes);
        }

        $this->eligibility->syncBusiness($business);
        $business = $business->fresh() ?? $business;

        $limit = round($overdraftLimit !== null && $overdraftLimit > 0 ? $overdraftLimit : $approvedAmount, 2);
        if ($limit < 1) {
            return ['ok' => false, 'message' => 'Overdraft limit must be at least ₦1.'];
        }

        $tierMax = $this->eligibility->tierMaxLimit($business->overdraft_volume_tier);
        $volume90d = (float) $business->overdraft_volume_90d;
        if ($tierMax <= 0) {
            return [
                'ok' => false,
                'message' => 'Business 90-day volume (₦'.number_format($volume90d, 2).') is below ₦'
                    .number_format($this->eligibility->tier1Threshold(), 0)
                    .'. Overdraft requires at least that volume (or Tier 2 at ₦'
                    .number_format($this->eligibility->tier2Threshold(), 0).').',
            ];
        }
        if ($limit > $tierMax + 0.0001) {
            return [
                'ok' => false,
                'message' => 'Limit ₦'.number_format($limit, 2).' exceeds tier max ₦'.number_format($tierMax, 2)
                    .' for this business (90d volume ₦'.number_format($volume90d, 2)
                    .', '.$business->overdraft_volume_tier.').',
            ];
        }
        if ($approvedAmount > $tierMax + 0.0001) {
            return [
                'ok' => false,
                'message' => 'Approved amount exceeds tier max ₦'.number_format($tierMax, 2).'.',
            ];
        }

        // Master must be able to cover the full limit if drawn.
        $capacity = $this->funding->availableCapacityForFunder($funder);
        if ($capacity + 0.0001 < $limit) {
            return [
                'ok' => false,
                'message' => 'Master loan account needs at least ₦'.number_format($limit, 2).' to back this overdraft. Available: ₦'.number_format($capacity, 2),
            ];
        }

        DB::transaction(function () use ($request, $business, $funder, $approvedAmount, $limit, $adminId, $adminNotes) {
            $business->update([
                'overdraft_limit' => $limit,
                'overdraft_approved_at' => now(),
                'overdraft_approved_by' => $adminId,
                'overdraft_status' => 'approved',
                'overdraft_funding_source' => OverdraftFundingService::FUNDING_MASTER_LOAN,
                'overdraft_funder_business_id' => $funder->id,
                'overdraft_requested_amount' => $approvedAmount,
                'overdraft_approval_notes' => $adminNotes,
                'overdraft_repayment_mode' => $business->overdraft_repayment_mode
                    ?: OverdraftInstallmentService::MODE_SINGLE,
            ]);

            $request->update([
                'status' => CreditFacilityRequest::STATUS_APPROVED,
                'funder_business_id' => $funder->id,
                'approved_amount' => $approvedAmount,
                'approved_at' => now(),
                'approved_by_admin_id' => $adminId,
                'admin_notes' => $adminNotes,
                'meta' => array_merge(is_array($request->meta) ? $request->meta : [], [
                    'overdraft_limit' => $limit,
                    'funding' => 'master_loan_draw_repay',
                ]),
            ]);
        });

        return [
            'ok' => true,
            'message' => 'Overdraft approved. Master account “'.$funder->name.'” will be debited when they draw and credited when they repay.',
        ];
    }

    /**
     * Loan: debit master now, credit borrower business (or wallet) now.
     *
     * @return array{ok: bool, message: string}
     */
    private function approveLoan(
        CreditFacilityRequest $request,
        Business $funder,
        float $approvedAmount,
        int $adminId,
        ?string $adminNotes,
    ): array {
        $wallet = $request->wallet;
        $business = $request->business_id
            ? Business::query()->find($request->business_id)
            : ($wallet ? $this->businessLedger->resolveLinkedOrMatchedBusiness($wallet) : null);

        try {
            $creditTarget = DB::transaction(function () use (
                $request,
                $funder,
                $business,
                $wallet,
                $approvedAmount,
                $adminId,
                $adminNotes
            ) {
                $lockedFunder = Business::query()->lockForUpdate()->findOrFail($funder->id);
                $available = round((float) $lockedFunder->balance, 2);
                if ($available + 0.0001 < $approvedAmount) {
                    throw new \RuntimeException(
                        'Master loan account has insufficient balance. Available: ₦'.number_format($available, 2)
                    );
                }

                $lockedFunder->decrement('balance', $approvedAmount);
                $lockedFunder->refresh();

                TransactionLog::create([
                    'transaction_id' => 'LOAN-OUT-'.$request->id.'-'.now()->timestamp,
                    'business_id' => $lockedFunder->id,
                    'event_type' => TransactionLog::EVENT_OVERDRAFT_FUNDING_DEBIT,
                    'description' => 'Loan disbursed to borrower: ₦'.number_format($approvedAmount, 2),
                    'metadata' => [
                        'credit_facility_request_id' => $request->id,
                        'borrower_business_id' => $business?->id,
                        'wallet_id' => $wallet?->id,
                        'amount' => $approvedAmount,
                    ],
                ]);

                $creditedWallet = null;
                if ($business) {
                    $lockedBorrower = Business::query()->lockForUpdate()->findOrFail($business->id);
                    $lockedBorrower->increment('balance', $approvedAmount);
                    $lockedBorrower->refresh();
                    $this->businessLedger->syncLinkedWalletsFromMerchantBalance($lockedBorrower);

                    TransactionLog::create([
                        'transaction_id' => 'LOAN-IN-'.$request->id.'-'.now()->timestamp,
                        'business_id' => $lockedBorrower->id,
                        'event_type' => TransactionLog::EVENT_OVERDRAFT_FUNDING_CREDIT,
                        'description' => 'Loan received from master account: ₦'.number_format($approvedAmount, 2),
                        'metadata' => [
                            'credit_facility_request_id' => $request->id,
                            'funder_business_id' => $lockedFunder->id,
                            'amount' => $approvedAmount,
                        ],
                    ]);

                    $creditedWallet = WhatsappWallet::query()
                        ->where('linked_business_id', $lockedBorrower->id)
                        ->where('status', WhatsappWallet::STATUS_ACTIVE)
                        ->orderByDesc('id')
                        ->first();
                } elseif ($wallet) {
                    $lockedWallet = WhatsappWallet::query()->lockForUpdate()->findOrFail($wallet->id);
                    $lockedWallet->balance = round((float) $lockedWallet->balance + $approvedAmount, 2);
                    $lockedWallet->save();

                    \App\Models\WhatsappWalletTransaction::query()->create([
                        'whatsapp_wallet_id' => $lockedWallet->id,
                        'sender_name' => (string) ($lockedFunder->name ?: 'Checkout loan'),
                        'type' => \App\Models\WhatsappWalletTransaction::TYPE_P2P_CREDIT,
                        'ledger_scope' => \App\Services\Consumer\ConsumerWalletTransactionScope::SCOPE_PERSONAL,
                        'amount' => $approvedAmount,
                        'balance_after' => (float) $lockedWallet->balance,
                        'meta' => [
                            'source' => 'credit_facility_loan',
                            'credit_facility_request_id' => $request->id,
                            'funder_business_id' => $lockedFunder->id,
                            'narration' => 'Loan from '.($lockedFunder->name ?: 'Checkout'),
                            'description' => 'Loan from '.($lockedFunder->name ?: 'Checkout'),
                        ],
                    ]);
                    $creditedWallet = $lockedWallet->fresh();
                } else {
                    throw new \RuntimeException('No borrower business or wallet to credit.');
                }

                $request->update([
                    'status' => CreditFacilityRequest::STATUS_APPROVED,
                    'business_id' => $business?->id ?? $request->business_id,
                    'funder_business_id' => $lockedFunder->id,
                    'approved_amount' => $approvedAmount,
                    'approved_at' => now(),
                    'approved_by_admin_id' => $adminId,
                    'admin_notes' => $adminNotes,
                    'meta' => array_merge(is_array($request->meta) ? $request->meta : [], [
                        'disbursed' => true,
                        'credit_target' => $business ? 'business_balance' : 'personal_wallet',
                    ]),
                ]);

                return $creditedWallet;
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        if ($creditTarget instanceof WhatsappWallet) {
            try {
                $amountLabel = WhatsappWalletMoneyFormatter::format($approvedAmount, 'NGN');
                $this->walletPush->notifyMoneyReceived(
                    $creditTarget,
                    $approvedAmount,
                    (float) $creditTarget->fresh()->balance,
                    sprintf('Your loan of %s was approved and credited.', $amountLabel),
                    [
                        'credit_source' => 'credit_facility_loan',
                        'sender_name' => (string) ($funder->name ?: 'Checkout'),
                        'currency' => 'NGN',
                    ],
                );
            } catch (\Throwable $e) {
                Log::warning('credit_facility_loan_push_failed', [
                    'request_id' => $request->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['ok' => true, 'message' => 'Loan approved and credited from master account “'.$funder->name.'”.'];
    }
}

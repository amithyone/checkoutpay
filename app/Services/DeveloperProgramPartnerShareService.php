<?php

namespace App\Services;

use App\Models\Business;
use App\Models\DeveloperProgramApplication;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\TransactionLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeveloperProgramPartnerShareService
{
    /**
     * Credit the developer partner's business balance with a share of platform fees (total_charges)
     * when the payment is approved, attributed, and an approved application exists. Idempotent per payment.
     */
    public function creditPartnerShareIfApplicable(Payment $payment): void
    {
        $paymentId = $payment->id;
        if (! $paymentId) {
            return;
        }

        try {
            DB::transaction(function () use ($paymentId) {
                /** @var Payment|null $locked */
                $locked = Payment::query()->whereKey($paymentId)->lockForUpdate()->first();

                if (! $locked || ! $locked->developer_program_partner_business_id) {
                    return;
                }
                if ($locked->developer_program_partner_share_credited_at !== null) {
                    return;
                }
                if ($locked->status !== Payment::STATUS_APPROVED) {
                    return;
                }

                $partnerId = (int) $locked->developer_program_partner_business_id;

                $application = DeveloperProgramApplication::query()
                    ->where('status', DeveloperProgramApplication::STATUS_APPROVED)
                    ->where(function ($q) use ($partnerId) {
                        $q->where('business_id', (string) $partnerId)
                            ->orWhere('business_id', $partnerId);
                    })
                    ->first();

                if (! $application) {
                    return;
                }

                $globalPercent = Setting::get('developer_program_fee_share_percent');
                $globalFloat = $globalPercent !== null && $globalPercent !== ''
                    ? (float) $globalPercent
                    : null;

                $sharePercent = $application->effectiveFeeSharePercent($globalFloat);
                if ($sharePercent === null || $sharePercent <= 0) {
                    return;
                }

                $totalCharges = (float) ($locked->total_charges ?? 0);
                $shareAmount = round($totalCharges * $sharePercent / 100, 2);
                if ($shareAmount <= 0) {
                    return;
                }

                /** @var Business|null $partnerBusiness */
                $partnerBusiness = Business::query()->whereKey($partnerId)->lockForUpdate()->first();
                if (! $partnerBusiness) {
                    return;
                }

                $partnerBusiness->increment('balance', $shareAmount);

                $locked->forceFill([
                    'developer_program_partner_share_amount' => $shareAmount,
                    'developer_program_partner_share_credited_at' => now(),
                ])->save();

                TransactionLog::create([
                    'transaction_id' => $locked->transaction_id,
                    'payment_id' => $locked->id,
                    'business_id' => $partnerId,
                    'event_type' => TransactionLog::EVENT_DEVELOPER_PROGRAM_PARTNER_SHARE_CREDITED,
                    'description' => 'Developer program partner fee share credited to partner balance.',
                    'metadata' => [
                        'payment_id' => $locked->id,
                        'merchant_business_id' => $locked->business_id,
                        'partner_business_id' => $partnerId,
                        'share_percent' => $sharePercent,
                        'base_total_charges' => $totalCharges,
                        'share_amount' => $shareAmount,
                    ],
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Developer program partner share credit failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

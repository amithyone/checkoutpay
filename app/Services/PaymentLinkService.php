<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\PaymentLinkPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentLinkService
{
    /**
     * @param  array{title: string, description?: string|null, amount?: float|null, currency?: string, reuse_mode: string}  $data
     */
    public function create(Business $business, array $data): PaymentLink
    {
        return PaymentLink::create([
            'business_id' => $business->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? 'NGN',
            'reuse_mode' => $data['reuse_mode'],
            'status' => PaymentLink::STATUS_ACTIVE,
        ]);
    }

    public function attachPayment(PaymentLink $link, Payment $payment): PaymentLinkPayment
    {
        return PaymentLinkPayment::create([
            'payment_link_id' => $link->id,
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
        ]);
    }

    /**
     * Tally an approved payment. Balance is credited by the match/approve path — do not credit here.
     */
    public function recordApproved(PaymentLink $link, Payment $payment): void
    {
        DB::transaction(function () use ($link, $payment) {
            $locked = PaymentLink::query()->whereKey($link->id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }

            $pivot = PaymentLinkPayment::query()
                ->where('payment_link_id', $locked->id)
                ->where('payment_id', $payment->id)
                ->lockForUpdate()
                ->first();

            if (! $pivot) {
                $pivot = PaymentLinkPayment::create([
                    'payment_link_id' => $locked->id,
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                ]);
            }

            if ($pivot->counted_at !== null) {
                return;
            }

            $pivot->update(['counted_at' => now()]);

            $amount = (float) $payment->amount;
            $updates = [
                'collected_amount' => (float) $locked->collected_amount + $amount,
                'collected_count' => (int) $locked->collected_count + 1,
            ];

            if ($locked->isOneTime() && $locked->status !== PaymentLink::STATUS_PAID) {
                $updates['status'] = PaymentLink::STATUS_PAID;
                $updates['paid_at'] = now();
            }

            $locked->update($updates);
        });

        Log::info('payment_link.approved', [
            'payment_link_id' => $link->id,
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
        ]);
    }

    public function pause(PaymentLink $link): void
    {
        if ($link->status !== PaymentLink::STATUS_ACTIVE) {
            return;
        }
        $link->update(['status' => PaymentLink::STATUS_PAUSED]);
    }

    public function resume(PaymentLink $link): void
    {
        if ($link->status !== PaymentLink::STATUS_PAUSED) {
            return;
        }
        $link->update(['status' => PaymentLink::STATUS_ACTIVE]);
    }
}

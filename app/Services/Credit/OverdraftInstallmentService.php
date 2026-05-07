<?php

namespace App\Services\Credit;

use App\Models\Business;
use App\Models\BusinessOverdraftInstallment;
use Illuminate\Support\Facades\DB;

class OverdraftInstallmentService
{
    public const MODE_SINGLE = 'single';

    public const MODE_SPLIT_30D = 'split_30d';

    /**
     * Start a 4-part weekly installment plan when the business first goes negative (split_30d).
     */
    public function startCycle(Business $business): void
    {
        if ($business->overdraft_repayment_mode !== self::MODE_SPLIT_30D) {
            return;
        }
        if ($business->overdraft_repayment_started_at) {
            return;
        }
        if ((float) $business->balance >= 0) {
            return;
        }

        DB::transaction(function () use ($business) {
            $locked = Business::query()->whereKey($business->id)->lockForUpdate()->first();
            if (! $locked || $locked->overdraft_repayment_started_at) {
                return;
            }
            if ((float) $locked->balance >= 0) {
                return;
            }

            $owed = min((float) $locked->overdraft_limit, abs((float) $locked->balance));
            if ($owed < 0.01) {
                return;
            }

            $parts = 4;
            $base = round($owed / $parts, 2);
            $start = now();

            for ($i = 1; $i <= $parts; $i++) {
                $amt = $i === $parts ? round($owed - $base * ($parts - 1), 2) : $base;
                BusinessOverdraftInstallment::create([
                    'business_id' => $locked->id,
                    'sequence' => $i,
                    'due_at' => $start->copy()->addDays(7 * ($i - 1)),
                    'amount_due' => $amt,
                    'amount_paid' => 0,
                    'status' => 'pending',
                ]);
            }

            $locked->update(['overdraft_repayment_started_at' => $start]);
        });
    }

    /**
     * Mark installments paid/overdue based on recovery toward zero balance.
     */
    public function syncInstallmentStatuses(Business $business): void
    {
        if (! $business->overdraft_repayment_started_at) {
            return;
        }

        $installments = $business->overdraftInstallments()->orderBy('sequence')->get();
        if ($installments->isEmpty()) {
            return;
        }

        $totalPlanned = (float) $installments->sum('amount_due');
        if ($totalPlanned < 0.01) {
            return;
        }

        $balance = (float) $business->balance;
        $owedNow = $balance < 0 ? min((float) $business->overdraft_limit, abs($balance)) : 0;
        $recovered = max(0, $totalPlanned - $owedNow);

        $cumulative = 0.0;
        foreach ($installments as $row) {
            $cumulative += (float) $row->amount_due;
            if ($recovered + 0.0001 >= $cumulative) {
                if ($row->status !== 'paid') {
                    $row->update([
                        'status' => 'paid',
                        'amount_paid' => $row->amount_due,
                    ]);
                }
            } elseif ($row->due_at->isPast() && $row->status === 'pending') {
                $row->update(['status' => 'overdue']);
            }
        }

        if ($balance >= 0) {
            foreach ($installments as $row) {
                if ($row->status !== 'paid') {
                    $row->update([
                        'status' => 'paid',
                        'amount_paid' => $row->amount_due,
                    ]);
                }
            }
        }
    }
}

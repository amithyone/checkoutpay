<?php

namespace App\Services\MevonPay;

use App\Models\MevonPayLedgerEntry;
use App\Services\MavonPayTransferService;
use Carbon\Carbon;

final class MevonPayReconciliationService
{
    public function __construct(
        private MevonPayBalanceSnapshotService $balanceSnapshot,
    ) {}

    /**
    * @return array{
    *   from: string,
    *   to: string,
    *   inbound_gross: float,
    *   inbound_fees: float,
    *   outbound_gross: float,
    *   outbound_fees: float,
    *   net_mevon_impact: float,
    *   live_naira_balance: ?float,
    *   live_naira_ledger: ?float,
    *   expected_balance_from_ledger: float,
    *   variance_vs_live_balance: ?float,
    *   variance_vs_live_ledger: ?float,
    *   within_tolerance: bool,
    *   tolerance: float,
    *   by_flow_type: array<string, array{count: int, inbound_gross: float, outbound_gross: float, net_impact: float}>,
    *   balance_ok: bool,
    *   balance_message: string
    * }
    */
    public function buildReport(Carbon $from, Carbon $to, ?float $openingBalance = null): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $tolerance = (float) config('mevonpay_fees.reconciliation_tolerance', 0.01);

        $rows = MevonPayLedgerEntry::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->get();

        $inboundGross = 0.0;
        $inboundFees = 0.0;
        $outboundGross = 0.0;
        $outboundFees = 0.0;
        $netImpact = 0.0;
        $byFlow = [];

        foreach ($rows as $row) {
            $flow = (string) $row->flow_type;
            if (! isset($byFlow[$flow])) {
                $byFlow[$flow] = ['count' => 0, 'inbound_gross' => 0.0, 'outbound_gross' => 0.0, 'net_impact' => 0.0];
            }
            $byFlow[$flow]['count']++;
            $byFlow[$flow]['net_impact'] += (float) $row->net_mevon_impact;
            $netImpact += (float) $row->net_mevon_impact;

            if ($row->direction === MevonPayLedgerEntry::DIRECTION_INBOUND) {
                $inboundGross += (float) $row->gross_amount;
                $inboundFees += (float) ($row->mevon_inbound_fee ?? 0);
                $byFlow[$flow]['inbound_gross'] += (float) $row->gross_amount;
            } else {
                if (in_array((string) $row->payout_bucket, [
                    MavonPayTransferService::BUCKET_SUCCESSFUL,
                    MavonPayTransferService::BUCKET_PENDING,
                ], true)) {
                    $outboundGross += (float) $row->gross_amount;
                    $outboundFees += (float) ($row->mevon_outbound_fee ?? 0);
                    $byFlow[$flow]['outbound_gross'] += (float) $row->gross_amount;
                }
            }
        }

        ksort($byFlow);

        $opening = $openingBalance ?? 0.0;
        $expectedFromLedger = round($opening + $netImpact, 2);

        $live = $this->balanceSnapshot->forDashboard();
        $liveBal = $live['naira_balance'] ?? null;
        $liveLedger = $live['naira_ledger'] ?? null;

        $varianceBal = $liveBal !== null ? round($liveBal - $expectedFromLedger, 2) : null;
        $varianceLedger = $liveLedger !== null ? round($liveLedger - $expectedFromLedger, 2) : null;

        $within = true;
        if ($varianceBal !== null && abs($varianceBal) > $tolerance) {
            $within = false;
        }
        if ($varianceLedger !== null && abs($varianceLedger) > $tolerance) {
            $within = false;
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'inbound_gross' => round($inboundGross, 2),
            'inbound_fees' => round($inboundFees, 2),
            'outbound_gross' => round($outboundGross, 2),
            'outbound_fees' => round($outboundFees, 2),
            'net_mevon_impact' => round($netImpact, 2),
            'live_naira_balance' => $liveBal,
            'live_naira_ledger' => $liveLedger,
            'expected_balance_from_ledger' => $expectedFromLedger,
            'opening_balance' => $opening,
            'variance_vs_live_balance' => $varianceBal,
            'variance_vs_live_ledger' => $varianceLedger,
            'within_tolerance' => $within,
            'tolerance' => $tolerance,
            'by_flow_type' => $byFlow,
            'balance_ok' => (bool) ($live['ok'] ?? false),
            'balance_message' => (string) ($live['message'] ?? ''),
            'entry_count' => $rows->count(),
        ];
    }

    /**
    * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<MevonPayLedgerEntry>
    */
    public function paginateLedger(Carbon $from, Carbon $to, ?string $direction = null, ?string $flowType = null, int $perPage = 50)
    {
        $q = MevonPayLedgerEntry::query()
            ->whereBetween('occurred_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc('occurred_at');

        if ($direction !== null && $direction !== '') {
            $q->where('direction', $direction);
        }
        if ($flowType !== null && $flowType !== '') {
            $q->where('flow_type', $flowType);
        }

        return $q->paginate($perPage)->withQueryString();
    }
}

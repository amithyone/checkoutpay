<?php

namespace App\Services\MevonPay;

use App\Models\MevonPayDiscrepancyAlert;
use App\Models\MevonPayLedgerEntry;
use App\Services\MavonPayTransferService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class MevonPayBalanceMonitorService
{
    public function __construct(
        private MevonPayReconBaselineService $baseline,
        private MevonPayBalanceSnapshotService $balanceSnapshot,
    ) {}

    /**
     * @return array{
     *   active: bool,
     *   baseline: array<string, mixed>,
     *   inbound_gross: float,
     *   inbound_fees: float,
     *   outbound_gross: float,
     *   outbound_fees: float,
     *   net_mevon_impact: float,
     *   expected_balance: float,
     *   live_naira_balance: ?float,
     *   variance_amount: ?float,
     *   within_tolerance: bool,
     *   tolerance: float,
     *   alert_count: int,
     *   entry_count: int,
     *   last_checked_at: ?string,
     *   balance_ok: bool,
     *   balance_message: string
     * }
     */
    public function summary(): array
    {
        $baselineInfo = $this->baseline->info();
        $tolerance = $this->tolerance();

        if (! $this->baseline->isActive()) {
            return array_merge($this->emptySummary($tolerance), [
                'active' => false,
                'baseline' => $baselineInfo,
            ]);
        }

        $totals = $this->ledgerTotalsSinceBaseline();
        $expected = round($this->baseline->openingBalance() + $totals['net_mevon_impact'], 2);

        $live = $this->balanceSnapshot->forDashboard();
        $liveBal = $live['naira_balance'] ?? null;
        $variance = $liveBal !== null ? round($liveBal - $expected, 2) : null;
        $within = $variance === null || abs($variance) <= $tolerance;

        $lastChecked = MevonPayDiscrepancyAlert::query()->max('checked_at')
            ?? MevonPayDiscrepancyAlert::query()->max('created_at');

        return [
            'active' => true,
            'baseline' => $baselineInfo,
            'inbound_gross' => $totals['inbound_gross'],
            'inbound_fees' => $totals['inbound_fees'],
            'outbound_gross' => $totals['outbound_gross'],
            'outbound_fees' => $totals['outbound_fees'],
            'net_mevon_impact' => $totals['net_mevon_impact'],
            'expected_balance' => $expected,
            'live_naira_balance' => $liveBal,
            'variance_amount' => $variance,
            'within_tolerance' => $within,
            'tolerance' => $tolerance,
            'alert_count' => MevonPayDiscrepancyAlert::query()->count(),
            'entry_count' => $totals['entry_count'],
            'last_checked_at' => $lastChecked ? Carbon::parse($lastChecked)->toIso8601String() : null,
            'balance_ok' => (bool) ($live['ok'] ?? false),
            'balance_message' => (string) ($live['message'] ?? ''),
        ];
    }

    /**
     * @return LengthAwarePaginator<MevonPayLedgerEntry>
     */
    public function ledgerWithRunningBalance(int $perPage = 50): LengthAwarePaginator
    {
        $baselineAt = $this->baseline->baselineAt();
        if ($baselineAt === null) {
            return MevonPayLedgerEntry::query()->whereRaw('1 = 0')->paginate($perPage);
        }

        $opening = $this->baseline->openingBalance();
        $runningMap = $this->buildRunningBalanceMap($baselineAt, $opening);

        $paginator = MevonPayLedgerEntry::query()
            ->with('source')
            ->where('occurred_at', '>=', $baselineAt)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->getCollection()->transform(function (MevonPayLedgerEntry $entry) use ($runningMap): MevonPayLedgerEntry {
            $entry->setAttribute('running_expected_balance', $runningMap[$entry->id] ?? null);

            return $entry;
        });

        return $paginator;
    }

    /**
     * @return Collection<int, MevonPayDiscrepancyAlert>
     */
    public function recentAlerts(int $limit = 25): Collection
    {
        return MevonPayDiscrepancyAlert::query()
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{
     *   ok: bool,
     *   skipped: bool,
     *   message: string,
     *   expected_balance: ?float,
     *   live_balance: ?float,
     *   variance_amount: ?float,
     *   within_tolerance: bool,
     *   alert_created: bool,
     *   alert_id: ?int
     * }
     */
    public function checkNow(
        string $trigger = MevonPayDiscrepancyAlert::TRIGGER_SCHEDULED,
        ?MevonPayLedgerEntry $afterEntry = null,
    ): array {
        if (! $this->baseline->isActive()) {
            return [
                'ok' => true,
                'skipped' => true,
                'message' => 'Baseline not initialized.',
                'expected_balance' => null,
                'live_balance' => null,
                'variance_amount' => null,
                'within_tolerance' => true,
                'alert_created' => false,
                'alert_id' => null,
            ];
        }

        $tolerance = $this->tolerance();
        $totals = $this->ledgerTotalsSinceBaseline();
        $expected = round($this->baseline->openingBalance() + $totals['net_mevon_impact'], 2);

        $live = $this->balanceSnapshot->forDashboard();
        if (! ($live['ok'] ?? false)) {
            return [
                'ok' => false,
                'skipped' => false,
                'message' => (string) ($live['message'] ?? 'Could not fetch live Mevon balance.'),
                'expected_balance' => $expected,
                'live_balance' => null,
                'variance_amount' => null,
                'within_tolerance' => true,
                'alert_created' => false,
                'alert_id' => null,
            ];
        }

        $liveBal = $live['naira_balance'] ?? null;
        if ($liveBal === null) {
            return [
                'ok' => false,
                'skipped' => false,
                'message' => 'Mevon balance API did not return naira_balance.',
                'expected_balance' => $expected,
                'live_balance' => null,
                'variance_amount' => null,
                'within_tolerance' => true,
                'alert_created' => false,
                'alert_id' => null,
            ];
        }

        $variance = round($liveBal - $expected, 2);
        $within = abs($variance) <= $tolerance;
        $alertCreated = false;
        $alertId = null;

        if (! $within) {
            $lastEntry = $afterEntry ?? MevonPayLedgerEntry::query()
                ->where('occurred_at', '>=', $this->baseline->baselineAt())
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->first();

            $alert = MevonPayDiscrepancyAlert::query()->create([
                'checked_at' => now(),
                'expected_balance' => $expected,
                'live_balance' => round($liveBal, 2),
                'variance_amount' => $variance,
                'tolerance' => $tolerance,
                'ledger_entry_id' => $lastEntry?->id,
                'trigger' => $trigger,
                'meta' => [
                    'entry_count' => $totals['entry_count'],
                    'last_entry_ref' => $lastEntry?->external_reference ?: $lastEntry?->payout_reference,
                    'balance_message' => (string) ($live['message'] ?? ''),
                ],
            ]);
            $alertCreated = true;
            $alertId = $alert->id;
        }

        return [
            'ok' => true,
            'skipped' => false,
            'message' => $within ? 'Balance within tolerance.' : 'Variance exceeds tolerance.',
            'expected_balance' => $expected,
            'live_balance' => round($liveBal, 2),
            'variance_amount' => $variance,
            'within_tolerance' => $within,
            'alert_created' => $alertCreated,
            'alert_id' => $alertId,
        ];
    }

    /**
     * @return array{
     *   inbound_gross: float,
     *   inbound_fees: float,
     *   outbound_gross: float,
     *   outbound_fees: float,
     *   net_mevon_impact: float,
     *   entry_count: int
     * }
     */
    private function ledgerTotalsSinceBaseline(): array
    {
        $baselineAt = $this->baseline->baselineAt();
        if ($baselineAt === null) {
            return [
                'inbound_gross' => 0.0,
                'inbound_fees' => 0.0,
                'outbound_gross' => 0.0,
                'outbound_fees' => 0.0,
                'net_mevon_impact' => 0.0,
                'entry_count' => 0,
            ];
        }

        $rows = MevonPayLedgerEntry::query()
            ->where('occurred_at', '>=', $baselineAt)
            ->get();

        $inboundGross = 0.0;
        $inboundFees = 0.0;
        $outboundGross = 0.0;
        $outboundFees = 0.0;
        $netImpact = 0.0;

        foreach ($rows as $row) {
            $netImpact += (float) $row->net_mevon_impact;

            if ($row->direction === MevonPayLedgerEntry::DIRECTION_INBOUND) {
                $inboundGross += (float) $row->gross_amount;
                $inboundFees += (float) ($row->mevon_inbound_fee ?? 0);
            } elseif (in_array((string) $row->payout_bucket, [
                MavonPayTransferService::BUCKET_SUCCESSFUL,
                MavonPayTransferService::BUCKET_PENDING,
            ], true)) {
                $outboundGross += (float) $row->gross_amount;
                $outboundFees += (float) ($row->mevon_outbound_fee ?? 0);
            }
        }

        return [
            'inbound_gross' => round($inboundGross, 2),
            'inbound_fees' => round($inboundFees, 2),
            'outbound_gross' => round($outboundGross, 2),
            'outbound_fees' => round($outboundFees, 2),
            'net_mevon_impact' => round($netImpact, 2),
            'entry_count' => $rows->count(),
        ];
    }

    /**
     * @return array<int, float>
     */
    private function buildRunningBalanceMap(Carbon $baselineAt, float $opening): array
    {
        $map = [];
        $running = $opening;

        MevonPayLedgerEntry::query()
            ->where('occurred_at', '>=', $baselineAt)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->select(['id', 'net_mevon_impact'])
            ->chunk(500, function ($rows) use (&$running, &$map): void {
                foreach ($rows as $row) {
                    $running += (float) $row->net_mevon_impact;
                    $map[$row->id] = round($running, 2);
                }
            });

        return $map;
    }

    private function tolerance(): float
    {
        return (float) config('mevonpay_fees.reconciliation_tolerance', 0.01);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(float $tolerance): array
    {
        return [
            'inbound_gross' => 0.0,
            'inbound_fees' => 0.0,
            'outbound_gross' => 0.0,
            'outbound_fees' => 0.0,
            'net_mevon_impact' => 0.0,
            'expected_balance' => 0.0,
            'live_naira_balance' => null,
            'variance_amount' => null,
            'within_tolerance' => true,
            'tolerance' => $tolerance,
            'alert_count' => 0,
            'entry_count' => 0,
            'last_checked_at' => null,
            'balance_ok' => false,
            'balance_message' => 'Monitoring not started.',
        ];
    }
}

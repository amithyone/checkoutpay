<?php

namespace App\Services\Admin;

use App\Models\VirtualCardRequest;
use App\Models\WhatsappWalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
final class AdminVirtualCardProfitService
{
    /**
     * @return array<string, mixed>
     */
    public function stats(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->date('from'))->startOfDay() : null;
        $to = $request->filled('to') ? Carbon::parse($request->date('to'))->endOfDay() : null;

        $feeRows = $this->feeTransactions($from, $to);
        $topupRows = $this->topupTransactions($from, $to);
        $withdrawRows = $this->withdrawTransactions($from, $to);

        $requestProfit = $this->sumProfitFromRows($feeRows, 'fee');
        $topupProfit = $this->sumProfitFromRows($topupRows, 'topup');
        $withdrawProfit = $this->sumProfitFromRows($withdrawRows, 'withdraw');

        $requestGross = $this->sumGrossFromRows($feeRows);
        $topupGross = $this->sumGrossFromRows($topupRows);
        $withdrawGross = $this->sumGrossFromRows($withdrawRows);

        $seriesFrom = $from ?? now()->subMonths(11)->startOfMonth();
        $seriesTo = $to ?? now()->endOfDay();

        return [
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'summary' => [
                'total_profit_ngn' => round($requestProfit + $topupProfit + $withdrawProfit, 2),
                'request_profit_ngn' => $requestProfit,
                'topup_profit_ngn' => $topupProfit,
                'withdraw_profit_ngn' => $withdrawProfit,
                'request_gross_ngn' => $requestGross,
                'topup_gross_ngn' => $topupGross,
                'withdraw_gross_ngn' => $withdrawGross,
                'request_count' => count($feeRows),
                'topup_count' => count($topupRows),
                'withdraw_count' => count($withdrawRows),
                'active_cards' => VirtualCardRequest::query()->where('status', VirtualCardRequest::STATUS_ACTIVE)->count(),
                'refunded_request_fees' => $this->countRefundedFees($from, $to),
            ],
            'monthly' => $this->monthlySeries($seriesFrom, $seriesTo),
        ];
    }

    /**
     * @return list<array{amount: float, meta: array<string, mixed>, created_at: string}>
     */
    private function feeTransactions(?Carbon $from, ?Carbon $to): array
    {
        return $this->loadTransactions(WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE, $from, $to);
    }

    /**
     * @return list<array{amount: float, meta: array<string, mixed>, created_at: string}>
     */
    private function topupTransactions(?Carbon $from, ?Carbon $to): array
    {
        return $this->loadTransactions(WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_TOPUP, $from, $to);
    }

    /**
     * @return list<array{amount: float, meta: array<string, mixed>, created_at: string}>
     */
    private function withdrawTransactions(?Carbon $from, ?Carbon $to): array
    {
        return $this->loadTransactions(WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_WITHDRAW, $from, $to);
    }

    /**
     * @return list<array{amount: float, meta: array<string, mixed>, created_at: string}>
     */
    private function loadTransactions(string $type, ?Carbon $from, ?Carbon $to): array
    {
        $query = WhatsappWalletTransaction::query()
            ->where('type', $type)
            ->orderBy('id');

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->get()->map(function (WhatsappWalletTransaction $txn) {
            $meta = is_array($txn->meta) ? $txn->meta : [];

            return [
                'amount' => abs((float) $txn->amount),
                'meta' => $meta,
                'created_at' => $txn->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        })->filter(function (array $row) {
            return ! ($row['meta']['refunded'] ?? false);
        })->values()->all();
    }

    private function countRefundedFees(?Carbon $from, ?Carbon $to): int
    {
        $query = WhatsappWalletTransaction::query()
            ->where('type', WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE)
            ->where('meta->refunded', true);

        if ($from) {
            $query->where('updated_at', '>=', $from);
        }
        if ($to) {
            $query->where('updated_at', '<=', $to);
        }

        return (int) $query->count();
    }

    /**
     * @param  list<array{amount: float, meta: array<string, mixed>}>  $rows
     */
    private function sumProfitFromRows(array $rows, string $kind): float
    {
        $total = 0.0;
        foreach ($rows as $row) {
            $total += $this->profitNgnFromMeta($row['meta'], $kind);
        }

        return round($total, 2);
    }

    /**
     * @param  list<array{amount: float, meta: array<string, mixed>}>  $rows
     */
    private function sumGrossFromRows(array $rows): float
    {
        return round(array_sum(array_map(fn (array $row) => (float) $row['amount'], $rows)), 2);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function profitNgnFromMeta(array $meta, string $kind): float
    {
        $usd = $this->profitUsdFromMeta($meta, $kind);
        $mid = (float) ($meta['fx_mid_usd_ngn'] ?? 0);

        if ($usd < 0.01 || $mid < 0.01) {
            return 0.0;
        }

        if ($kind === 'withdraw') {
            $buy = (float) ($meta['buy_rate'] ?? 0);
            if ($buy <= 0 || $mid <= $buy) {
                return 0.0;
            }

            return round($usd * ($mid - $buy), 2);
        }

        $sell = (float) ($meta['sell_rate'] ?? 0);
        if ($sell <= $mid) {
            return 0.0;
        }

        return round($usd * ($sell - $mid), 2);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function profitUsdFromMeta(array $meta, string $kind): float
    {
        if ($kind === 'fee') {
            $creation = (float) ($meta['creation_fee_usd'] ?? 0);
            $load = (float) ($meta['initial_load_usd'] ?? 0);
            if ($creation > 0 && $load > 0) {
                return round($creation + $load, 2);
            }

            return (float) ($meta['fee_usd'] ?? 0);
        }

        return (float) ($meta['amount_usd'] ?? $meta['fee_usd'] ?? 0);
    }

    /**
     * @return list<array{month: string, label: string, request_profit: float, topup_profit: float, withdraw_profit: float, total_profit: float}>
     */
    private function monthlySeries(Carbon $from, Carbon $to): array
    {
        $buckets = [];
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->endOfMonth();

        while ($cursor <= $end) {
            $key = $cursor->format('Y-m');
            $buckets[$key] = [
                'month' => $key,
                'label' => $cursor->format('M Y'),
                'request_profit' => 0.0,
                'topup_profit' => 0.0,
                'withdraw_profit' => 0.0,
                'total_profit' => 0.0,
            ];
            $cursor->addMonth();
        }

        $types = [
            'request_profit' => WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE,
            'topup_profit' => WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_TOPUP,
            'withdraw_profit' => WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_WITHDRAW,
        ];

        foreach ($types as $bucketKey => $type) {
            $rows = WhatsappWalletTransaction::query()
                ->where('type', $type)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<=', $to)
                ->orderBy('id')
                ->get();

            foreach ($rows as $txn) {
                $meta = is_array($txn->meta) ? $txn->meta : [];
                if ($meta['refunded'] ?? false) {
                    continue;
                }
                $month = $txn->created_at?->format('Y-m');
                if (! is_string($month) || ! isset($buckets[$month])) {
                    continue;
                }
                $kind = match ($type) {
                    WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE => 'fee',
                    WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_TOPUP => 'topup',
                    default => 'withdraw',
                };
                $profit = $this->profitNgnFromMeta($meta, $kind);
                $buckets[$month][$bucketKey] = round($buckets[$month][$bucketKey] + $profit, 2);
                $buckets[$month]['total_profit'] = round($buckets[$month]['total_profit'] + $profit, 2);
            }
        }

        return array_values($buckets);
    }
}

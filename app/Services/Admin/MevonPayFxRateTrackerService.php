<?php

namespace App\Services\Admin;

use App\Models\MevonPayFxRateSnapshot;
use App\Models\WhatsappWalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

final class MevonPayFxRateTrackerService
{
    private const DEDUP_MINUTES = 1;

    private const DEDUP_EPSILON = 0.0001;

    /** @var list<string> */
    private const LIVE_RANGES = ['1h', '6h', '7h', '12h', '24h'];

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Request $request): array
    {
        if (MevonPayFxRateSnapshot::query()->count() === 0) {
            $this->backfillFromTransactions();
        }

        $range = $this->normalizeRange((string) $request->query('range', '1h'));
        $from = $this->rangeStart($range);
        $to = now();

        $series = $this->series($from, $to, $range);
        $latest = MevonPayFxRateSnapshot::query()->orderByDesc('recorded_at')->first();
        $published = $this->publishedCurrent();
        $wallet = app(\App\Services\MevonPay\MevonPayBalanceSnapshotService::class)->forDashboard();

        return [
            'range' => $range,
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'wallet' => $wallet,
            'max_fx_usd_per_op' => app(MevonPayAdminFxConversionService::class)->maxUsdPerOp(),
            'current' => $this->currentSnapshot($latest, $published),
            'change' => $this->changeWindows($latest),
            'stats' => $this->periodStats($from, $to),
            'series' => $series,
            'recent' => $this->recentRows(25),
            'live_poll' => $this->isLiveRange($range),
            'poll_seconds' => 60,
        ];
    }

    /**
     * USD/NGN rates for the public marketing calculator — mirrors admin rate tracker hero cards.
     *
     * @return array{ok: bool, sell_rate: ?float, buy_rate: ?float, mid: ?float, mevon_mid: ?float, spread: ?float, published_at: ?string, updated_at: string, poll_seconds: int}
     */
    public function calculatorRates(bool $fetchFresh = false): array
    {
        if ($fetchFresh) {
            try {
                app(\App\Services\MevonPay\MevonPayExchangeRateService::class)->ngnPerUsdFresh();
            } catch (\Throwable) {
                // Poll must still return the latest stored rates.
            }
        }

        $latest = MevonPayFxRateSnapshot::query()->orderByDesc('recorded_at')->first();
        $published = $this->publishedCurrent();
        $current = $this->currentSnapshot($latest, $published);

        $mid = $current['published_mid'] ?? $current['mevon_mid'];
        $sell = $current['sell_rate'];
        $buy = $current['buy_rate'];

        if ($mid !== null && ($sell === null || $buy === null)) {
            $computed = app(\App\Services\Consumer\VirtualCardFxPublishService::class)->ratesForMid((float) $mid);
            $sell ??= $computed['sell_rate'];
            $buy ??= $computed['buy_rate'];
        }

        return [
            'ok' => true,
            'sell_rate' => $sell,
            'buy_rate' => $buy,
            'mid' => $mid,
            'mevon_mid' => $current['mevon_mid'],
            'spread' => ($sell !== null && $buy !== null) ? round($sell - $buy, 4) : null,
            'published_at' => $current['published_at'],
            'updated_at' => now()->toIso8601String(),
            'poll_seconds' => 60,
        ];
    }

    /**
     * Fresh Mevon read + chart payload for admin live polling.
     *
     * @return array<string, mixed>
     */
    public function liveData(Request $request, bool $fetchFresh = true): array
    {
        if (MevonPayFxRateSnapshot::query()->count() === 0) {
            $this->backfillFromTransactions();
        }

        if ($fetchFresh) {
            try {
                app(\App\Services\MevonPay\MevonPayExchangeRateService::class)->ngnPerUsdFresh();
            } catch (\Throwable) {
                // Poll must still return the latest stored series.
            }
        }

        $range = $this->normalizeRange((string) $request->query('range', '1h'));
        $from = $this->rangeStart($range);
        $to = now();
        $latest = MevonPayFxRateSnapshot::query()->orderByDesc('recorded_at')->first();
        $published = $this->publishedCurrent();
        $wallet = app(\App\Services\MevonPay\MevonPayBalanceSnapshotService::class)->forDashboard();

        return [
            'ok' => true,
            'range' => $range,
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'updated_at' => $to->toIso8601String(),
            'wallet' => $wallet,
            'current' => $this->currentSnapshot($latest, $published),
            'stats' => $this->periodStats($from, $to),
            'series' => $this->series($from, $to, $range),
            'live_poll' => $this->isLiveRange($range),
            'poll_seconds' => 60,
        ];
    }

    public function recordLive(float $mevonMid, ?float $publishedMid = null, ?float $sellRate = null, ?float $buyRate = null, string $source = 'mevon_live'): void
    {
        if ($mevonMid <= 0) {
            return;
        }

        $mid = $publishedMid ?? $mevonMid;
        if ($this->shouldSkipDuplicate($mevonMid, $mid, $source)) {
            return;
        }

        $this->insertSnapshot($mevonMid, $mid, $sellRate, $buyRate, $source);
    }

    public function recordPublished(float $publishedMid, ?float $sellRate, ?float $buyRate, string $source, ?float $mevonMid = null): void
    {
        if ($publishedMid <= 0) {
            return;
        }

        $live = $mevonMid ?? $publishedMid;
        if ($this->shouldSkipDuplicate($live, $publishedMid, 'publish_'.$source)) {
            return;
        }

        $this->insertSnapshot($live, $publishedMid, $sellRate, $buyRate, $source);
    }

    public function backfillFromTransactions(): int
    {
        $types = [
            WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE,
            WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_TOPUP,
            WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_WITHDRAW,
        ];

        $rows = WhatsappWalletTransaction::query()
            ->whereIn('type', $types)
            ->whereNotNull('meta')
            ->orderBy('created_at')
            ->get(['id', 'meta', 'created_at']);

        $inserted = 0;
        foreach ($rows as $row) {
            $meta = is_array($row->meta) ? $row->meta : [];
            $mid = isset($meta['fx_mid_usd_ngn']) ? (float) $meta['fx_mid_usd_ngn'] : 0;
            if ($mid <= 0) {
                continue;
            }

            $sell = isset($meta['sell_rate']) ? (float) $meta['sell_rate'] : null;
            $buy = isset($meta['buy_rate']) ? (float) $meta['buy_rate'] : null;
            $recordedAt = $row->created_at ?? now();

            if ($this->snapshotExistsNear($recordedAt, $mid)) {
                continue;
            }

            $this->insertSnapshot($mid, $mid, $sell, $buy, 'transaction_backfill', $recordedAt);
            $inserted++;
        }

        return $inserted;
    }

    private function insertSnapshot(
        float $mevonMid,
        float $publishedMid,
        ?float $sellRate,
        ?float $buyRate,
        string $source,
        ?Carbon $recordedAt = null,
    ): void {
        $previous = MevonPayFxRateSnapshot::query()->orderByDesc('recorded_at')->first();
        $changeAbs = null;
        $changePct = null;

        if ($previous !== null) {
            $base = (float) ($previous->mevon_mid ?? $previous->published_mid);
            if ($base > 0) {
                $changeAbs = round($mevonMid - $base, 4);
                $changePct = round(($changeAbs / $base) * 100, 4);
            }
        }

        MevonPayFxRateSnapshot::query()->create([
            'recorded_at' => $recordedAt ?? now(),
            'mevon_mid' => round($mevonMid, 4),
            'published_mid' => round($publishedMid, 4),
            'sell_rate' => $sellRate !== null ? round($sellRate, 4) : null,
            'buy_rate' => $buyRate !== null ? round($buyRate, 4) : null,
            'source' => $source,
            'change_abs' => $changeAbs,
            'change_pct' => $changePct,
        ]);
    }

    private function shouldSkipDuplicate(float $mevonMid, float $publishedMid, string $source): bool
    {
        $last = MevonPayFxRateSnapshot::query()->orderByDesc('recorded_at')->first();
        if ($last === null) {
            return false;
        }

        $sameRate = abs((float) ($last->mevon_mid ?? 0) - $mevonMid) < self::DEDUP_EPSILON
            && abs((float) $last->published_mid - $publishedMid) < self::DEDUP_EPSILON;

        if (! $sameRate) {
            return false;
        }

        $lastAt = $this->recordedAtCarbon($last);

        return $lastAt !== null && $lastAt->diffInMinutes(now()) < self::DEDUP_MINUTES;
    }

    private function snapshotExistsNear(Carbon $at, float $mid): bool
    {
        return MevonPayFxRateSnapshot::query()
            ->whereBetween('recorded_at', [$at->copy()->subMinutes(5), $at->copy()->addMinutes(5)])
            ->whereBetween('published_mid', [$mid - 0.01, $mid + 0.01])
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function currentSnapshot(?MevonPayFxRateSnapshot $latest, array $published): array
    {
        $mevon = $latest?->mevon_mid ?? $published['live_mevon'] ?? $published['mid'];
        $mid = $latest?->published_mid ?? $published['mid'];
        $sell = $latest?->sell_rate ?? $published['sell_rate'];
        $buy = $latest?->buy_rate ?? $published['buy_rate'];

        return [
            'mevon_mid' => $mevon,
            'published_mid' => $mid,
            'sell_rate' => $sell,
            'buy_rate' => $buy,
            'spread' => ($sell !== null && $buy !== null) ? round($sell - $buy, 4) : null,
            'sell_markup' => ($sell !== null && $mid !== null && $sell > $mid) ? round($sell - $mid, 4) : null,
            'buy_discount' => ($buy !== null && $mid !== null && $mid > $buy) ? round($mid - $buy, 4) : null,
            'source' => $latest?->source ?? $published['source'],
            'recorded_at' => $this->recordedAtIso($latest),
            'published_at' => $published['published_at'] ?? null,
        ];
    }

    /**
     * @return array<string, array{abs: ?float, pct: ?float, from: ?float}>
     */
    private function changeWindows(?MevonPayFxRateSnapshot $latest): array
    {
        $current = $latest?->mevon_mid ?? $latest?->published_mid;

        return [
            '24h' => $this->changeSince($current, now()->subDay()),
            '7d' => $this->changeSince($current, now()->subDays(7)),
            '30d' => $this->changeSince($current, now()->subDays(30)),
            '90d' => $this->changeSince($current, now()->subDays(90)),
        ];
    }

    /**
     * @return array{abs: ?float, pct: ?float, from: ?float}
     */
    private function changeSince(?float $current, Carbon $since): array
    {
        if ($current === null || $current <= 0) {
            return ['abs' => null, 'pct' => null, 'from' => null];
        }

        $past = MevonPayFxRateSnapshot::query()
            ->where('recorded_at', '<=', $since)
            ->orderByDesc('recorded_at')
            ->first();

        if ($past === null) {
            $past = MevonPayFxRateSnapshot::query()->orderBy('recorded_at')->first();
        }

        if ($past === null) {
            return ['abs' => null, 'pct' => null, 'from' => null];
        }

        $from = (float) ($past->mevon_mid ?? $past->published_mid);
        if ($from <= 0) {
            return ['abs' => null, 'pct' => null, 'from' => null];
        }

        $abs = round($current - $from, 4);

        return [
            'abs' => $abs,
            'pct' => round(($abs / $from) * 100, 4),
            'from' => $from,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function periodStats(Carbon $from, Carbon $to): array
    {
        $rows = MevonPayFxRateSnapshot::query()
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'count' => 0,
                'high' => null,
                'low' => null,
                'avg' => null,
                'volatility' => null,
                'total_abs_change' => null,
            ];
        }

        $values = $rows->map(fn (MevonPayFxRateSnapshot $r) => (float) ($r->mevon_mid ?? $r->published_mid))->all();
        $avg = array_sum($values) / count($values);
        $variance = 0.0;
        foreach ($values as $v) {
            $variance += ($v - $avg) ** 2;
        }
        $variance = count($values) > 1 ? $variance / (count($values) - 1) : 0.0;

        $first = $values[0];
        $last = $values[count($values) - 1];

        return [
            'count' => count($values),
            'high' => round(max($values), 4),
            'low' => round(min($values), 4),
            'avg' => round($avg, 4),
            'volatility' => round(sqrt($variance), 4),
            'total_abs_change' => round($last - $first, 4),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function series(Carbon $from, Carbon $to, string $range): array
    {
        $rows = MevonPayFxRateSnapshot::query()
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        if (in_array($range, self::LIVE_RANGES, true)) {
            return $this->mapSeriesPoints($rows, $range);
        }

        $bucket = match ($range) {
            '7d' => 'hour',
            '30d', '90d' => 'day',
            default => 'day',
        };

        $grouped = [];
        foreach ($rows as $row) {
            $at = $this->recordedAtCarbon($row);
            if ($at === null) {
                continue;
            }
            $key = $bucket === 'hour'
                ? $at->format('Y-m-d H:00')
                : $at->format('Y-m-d');
            $grouped[$key] = $row;
        }

        return array_values(array_map(function (MevonPayFxRateSnapshot $row) use ($range) {
            return $this->pointFromRow($row, $range);
        }, $grouped));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MevonPayFxRateSnapshot>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapSeriesPoints($rows, string $range = '24h'): array
    {
        return $rows->map(fn (MevonPayFxRateSnapshot $row) => $this->pointFromRow($row, $range))->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function pointFromRow(MevonPayFxRateSnapshot $row, string $range = '24h'): array
    {
        $at = $this->recordedAtCarbon($row);

        return [
            't' => $at?->toIso8601String(),
            'label' => $at?->format($this->seriesLabelFormat($range)) ?? '',
            'mevon_mid' => $row->mevon_mid,
            'published_mid' => $row->published_mid,
            'sell_rate' => $row->sell_rate,
            'buy_rate' => $row->buy_rate,
            'change_pct' => $row->change_pct,
            'source' => $row->source,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentRows(int $limit): array
    {
        return MevonPayFxRateSnapshot::query()
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get()
            ->map(fn (MevonPayFxRateSnapshot $row) => [
                'id' => $row->id,
                'recorded_at' => $this->recordedAtCarbon($row)?->format('M j, Y g:i:s'),
                'mevon_mid' => $row->mevon_mid,
                'published_mid' => $row->published_mid,
                'sell_rate' => $row->sell_rate,
                'buy_rate' => $row->buy_rate,
                'change_abs' => $row->change_abs,
                'change_pct' => $row->change_pct,
                'source' => $row->source,
            ])
            ->all();
    }

    /**
     * @return array{mid: ?float, sell_rate: ?float, buy_rate: ?float, published_at: ?string, source: ?string, live_mevon: ?float}
     */
    private function publishedCurrent(): array
    {
        $publish = app(\App\Services\Consumer\VirtualCardFxPublishService::class);
        $snap = $publish->publishedSnapshot();

        $live = null;
        try {
            $live = app(\App\Services\MevonPay\MevonPayExchangeRateService::class)->ngnPerUsd();
        } catch (\Throwable) {
            $live = null;
        }

        return array_merge($snap, ['live_mevon' => $live]);
    }

    private function recordedAtCarbon(?MevonPayFxRateSnapshot $row): ?Carbon
    {
        if ($row === null || $row->recorded_at === null) {
            return null;
        }

        return $row->recorded_at instanceof Carbon
            ? $row->recorded_at
            : Carbon::parse((string) $row->recorded_at);
    }

    private function recordedAtIso(?MevonPayFxRateSnapshot $row): ?string
    {
        return $this->recordedAtCarbon($row)?->toIso8601String();
    }

    private function normalizeRange(string $range): string
    {
        $allowed = ['1h', '6h', '7h', '12h', '24h', '7d', '30d', '90d', 'all'];

        return in_array($range, $allowed, true) ? $range : '1h';
    }

    private function isLiveRange(string $range): bool
    {
        return in_array($range, self::LIVE_RANGES, true);
    }

    private function seriesLabelFormat(string $range): string
    {
        return match ($range) {
            '1h' => 'g:i:s A',
            '6h', '7h', '12h' => 'g:i A',
            default => 'M j, g:i A',
        };
    }

    private function rangeStart(string $range): Carbon
    {
        return match ($range) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '7h' => now()->subHours(7),
            '12h' => now()->subHours(12),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => Carbon::parse(MevonPayFxRateSnapshot::query()->min('recorded_at') ?? now()->subYear()),
        };
    }
}

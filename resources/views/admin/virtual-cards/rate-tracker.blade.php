@extends('layouts.admin')

@section('title', 'MevonPay FX rate tracker')
@section('page-title', 'MevonPay FX rate tracker')

@section('content')
@php
    $current = $tracker['current'];
    $change = $tracker['change'];
    $stats = $tracker['stats'];
    $series = $tracker['series'];
    $recent = $tracker['recent'];
    $range = $tracker['range'];
    $livePoll = $tracker['live_poll'] ?? false;
    $pollSeconds = $tracker['poll_seconds'] ?? 60;
    $fmt = fn (?float $v, int $dec = 2) => $v !== null ? '₦'.number_format($v, $dec) : '—';
    $deltaClass = fn (?float $v) => $v === null ? 'text-gray-500' : ($v >= 0 ? 'text-emerald-700' : 'text-red-600');
    $deltaPrefix = fn (?float $v) => $v === null ? '' : ($v > 0 ? '+' : '');
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">MevonPay USD/NGN rate tracker</h2>
            <p class="text-sm text-gray-600 mt-1">Live Mevon mid, published sell/buy spreads, and historical movement</p>
        </div>
        <div class="flex flex-wrap gap-3 items-center">
            <form method="POST" action="{{ route('admin.virtual-cards.rate-tracker.refresh') }}">
                @csrf
                <input type="hidden" name="range" value="{{ $range }}">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary hover:opacity-90 text-white text-sm font-medium shadow-sm transition">
                    <i class="fas fa-bolt"></i> Sync live rate
                </button>
            </form>
            <a href="{{ route('admin.virtual-cards.index') }}" class="text-sm text-gray-600 hover:text-gray-900 font-medium">
                <i class="fas fa-arrow-left mr-1"></i> Card management
            </a>
            <a href="{{ route('admin.virtual-cards.stats') }}" class="text-sm text-emerald-700 hover:underline font-medium">
                <i class="fas fa-chart-pie mr-1"></i> Profit stats
            </a>
        </div>
    </div>

    {{-- Hero ticker panel (light theme — matches other admin card pages) --}}
    <div class="rounded-lg border-2 border-indigo-200 bg-gradient-to-br from-indigo-50 via-white to-blue-50 p-6 sm:p-8 shadow-sm">
        <div class="space-y-6">
            <div>
                <p class="text-xs uppercase tracking-wider text-indigo-700 font-semibold mb-2">Mevon live mid · NGN per 1 USD</p>
                <p id="fx-live-mevon" class="text-4xl sm:text-5xl font-bold text-gray-900 tabular-nums tracking-tight font-mono">
                    {{ $current['mevon_mid'] !== null ? number_format($current['mevon_mid'], 2) : '—' }}
                </p>
                <div class="flex flex-wrap gap-3 mt-4 text-sm">
                    @foreach (['24h' => '24H', '7d' => '7D', '30d' => '30D'] as $key => $label)
                        @php $c = $change[$key] ?? []; @endphp
                        <div class="rounded-lg bg-white border border-gray-200 px-3 py-2 min-w-[5.5rem] shadow-sm">
                            <span class="text-gray-500 text-xs block mb-0.5">{{ $label }}</span>
                            <div class="font-mono font-semibold leading-tight">
                                <span class="{{ $deltaClass($c['pct'] ?? null) }}">
                                    {{ isset($c['pct']) ? $deltaPrefix($c['pct']).number_format($c['pct'], 2).'%' : '—' }}
                                </span>
                                @if(isset($c['abs']))
                                    <span class="text-gray-500 text-xs block mt-0.5">({{ $deltaPrefix($c['abs']).number_format($c['abs'], 2) }})</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4 sm:p-5 shadow-sm min-h-[7.5rem] flex flex-col justify-between">
                    <p class="text-xs uppercase tracking-wider text-gray-500 font-medium">Published mid</p>
                    <p id="fx-published-mid" class="text-2xl font-bold text-gray-900 font-mono mt-2 tabular-nums">{{ $current['published_mid'] !== null ? number_format($current['published_mid'], 2) : '—' }}</p>
                    <p class="text-xs text-gray-500 mt-2">App mid rate</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-4 sm:p-5 shadow-sm min-h-[7.5rem] flex flex-col justify-between">
                    <p class="text-xs uppercase tracking-wider text-emerald-800 font-medium">Sell rate</p>
                    <p id="fx-sell-rate" class="text-2xl font-bold text-emerald-800 font-mono mt-2 tabular-nums">{{ $current['sell_rate'] !== null ? number_format($current['sell_rate'], 2) : '—' }}</p>
                    <p class="text-xs text-emerald-700 mt-2">+{{ $current['sell_markup'] !== null ? number_format($current['sell_markup'], 2) : '—' }} markup</p>
                </div>
                <div class="rounded-lg border border-violet-200 bg-violet-50/60 p-4 sm:p-5 shadow-sm min-h-[7.5rem] flex flex-col justify-between">
                    <p class="text-xs uppercase tracking-wider text-violet-800 font-medium">Buy rate</p>
                    <p id="fx-buy-rate" class="text-2xl font-bold text-violet-800 font-mono mt-2 tabular-nums">{{ $current['buy_rate'] !== null ? number_format($current['buy_rate'], 2) : '—' }}</p>
                    <p class="text-xs text-violet-700 mt-2">−{{ $current['buy_discount'] !== null ? number_format($current['buy_discount'], 2) : '—' }} discount</p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-4 sm:p-5 shadow-sm min-h-[7.5rem] flex flex-col justify-between">
                    <p class="text-xs uppercase tracking-wider text-amber-800 font-medium">Spread</p>
                    <p id="fx-spread" class="text-2xl font-bold text-amber-900 font-mono mt-2 tabular-nums">{{ $current['spread'] !== null ? number_format($current['spread'], 2) : '—' }}</p>
                    <p class="text-xs text-amber-800 mt-2 truncate" title="{{ $current['source'] ?? 'unknown' }}">{{ $current['source'] ?? 'unknown' }}</p>
                </div>
            </div>
        </div>

        <p id="fx-last-snapshot" class="text-xs text-gray-500 mt-6">
            @if(!empty($current['recorded_at']))
                Last snapshot {{ \Carbon\Carbon::parse($current['recorded_at'])->diffForHumans() }}
                · {{ \Carbon\Carbon::parse($current['recorded_at'])->format('M j, Y g:i:s A') }}
            @else
                No snapshots yet — sync live rate or wait for automatic capture.
            @endif
            @if(!empty($current['published_at']))
                · App published {{ \Carbon\Carbon::parse($current['published_at'])->diffForHumans() }}
            @endif
        </p>
    </div>

    {{-- Range + period stats --}}
    <div class="grid grid-cols-1 xl:grid-cols-4 gap-4">
        <div class="xl:col-span-1 bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-gray-500 mb-3">Time range</p>
            <div class="flex flex-wrap gap-2">
                @foreach (['1h' => '1H', '6h' => '6H', '7h' => '7H', '12h' => '12H', '24h' => '24H', '7d' => '7D', '30d' => '30D', '90d' => '90D', 'all' => 'ALL'] as $key => $label)
                    <a href="{{ route('admin.virtual-cards.rate-tracker', ['range' => $key]) }}"
                       class="px-3 py-1.5 rounded-lg text-sm font-medium transition border {{ $range === $key ? 'bg-indigo-100 text-indigo-900 border-indigo-300 shadow-sm' : 'bg-gray-100 text-gray-700 border-gray-200 hover:bg-gray-200' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            <dl id="fx-period-stats" class="mt-5 space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Snapshots</dt><dd data-stat="count" class="font-mono font-semibold text-gray-900">{{ number_format($stats['count'] ?? 0) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Period high</dt><dd data-stat="high" class="font-mono text-emerald-700">{{ $fmt($stats['high'] ?? null) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Period low</dt><dd data-stat="low" class="font-mono text-red-600">{{ $fmt($stats['low'] ?? null) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Average</dt><dd data-stat="avg" class="font-mono text-gray-900">{{ $fmt($stats['avg'] ?? null) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Volatility (σ)</dt><dd data-stat="volatility" class="font-mono text-amber-700">{{ $stats['volatility'] !== null ? number_format($stats['volatility'], 2) : '—' }}</dd></div>
                <div class="flex justify-between border-t border-gray-100 pt-3"><dt class="text-gray-500">Net change</dt><dd data-stat="total_abs_change" class="font-mono font-semibold {{ $deltaClass($stats['total_abs_change'] ?? null) }}">{{ isset($stats['total_abs_change']) ? $deltaPrefix($stats['total_abs_change']).number_format($stats['total_abs_change'], 2) : '—' }}</dd></div>
            </dl>
        </div>

        <div class="xl:col-span-3 relative rounded-lg border border-gray-200 bg-white p-5 shadow-sm overflow-hidden">
            <div id="fx-live-badge" class="absolute top-3 right-4 flex items-center gap-2 text-[10px] uppercase tracking-wider {{ $livePoll ? 'text-indigo-700' : 'text-gray-500' }}">
                <span id="fx-live-dot" class="inline-block w-2 h-2 rounded-full {{ $livePoll ? 'bg-indigo-500 animate-pulse' : 'bg-gray-400' }}"></span>
                <span id="fx-live-label">{{ $livePoll ? 'Live · updates every '.$pollSeconds.'s' : 'Historical' }}</span>
                <span id="fx-live-countdown" class="text-gray-400 normal-case {{ $livePoll ? '' : 'hidden' }}"></span>
            </div>
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Rate movement</h3>
            <p class="text-xs text-gray-500 mb-4">Mevon mid (blue) · Sell (green) · Buy (violet) · Published mid (gray)</p>
            <div class="h-80 sm:h-96">
                <canvas id="fxRateChart"></canvas>
            </div>
            <div id="fx-chart-empty" class="absolute inset-0 flex items-center justify-center bg-white/90 rounded-lg {{ empty($series) ? '' : 'hidden' }}">
                <p class="text-gray-500 text-sm">No rate history in this range. Sync live rate to start tracking.</p>
            </div>
        </div>
    </div>

    {{-- Recent snapshots table --}}
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Recent snapshots</h3>
            <span class="text-xs text-gray-500">{{ count($recent) }} rows · live poll every 60s · 1 min dedup if unchanged</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Recorded</th>
                        <th class="px-4 py-3">Mevon mid</th>
                        <th class="px-4 py-3">Published</th>
                        <th class="px-4 py-3">Sell</th>
                        <th class="px-4 py-3">Buy</th>
                        <th class="px-4 py-3">Change</th>
                        <th class="px-4 py-3">Source</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-mono text-xs sm:text-sm">
                    @forelse($recent as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $row['recorded_at'] }}</td>
                            <td class="px-4 py-3 font-semibold text-indigo-700">{{ $row['mevon_mid'] !== null ? number_format($row['mevon_mid'], 2) : '—' }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ number_format($row['published_mid'], 2) }}</td>
                            <td class="px-4 py-3 text-emerald-700">{{ $row['sell_rate'] !== null ? number_format($row['sell_rate'], 2) : '—' }}</td>
                            <td class="px-4 py-3 text-violet-700">{{ $row['buy_rate'] !== null ? number_format($row['buy_rate'], 2) : '—' }}</td>
                            <td class="px-4 py-3 {{ $deltaClass($row['change_pct']) }}">
                                @if($row['change_pct'] !== null)
                                    {{ ($row['change_pct'] > 0 ? '+' : '').number_format($row['change_pct'], 3) }}%
                                    <span class="text-gray-400">({{ ($row['change_abs'] > 0 ? '+' : '').number_format($row['change_abs'], 2) }})</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $row['source'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500">No snapshots recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-xs text-gray-600 leading-relaxed">
        <p class="font-semibold text-gray-800 mb-1">How tracking works</p>
        <p>Rates are captured when MevonPay is queried live (cached fetch) and when app FX is published. Historical card transactions are backfilled once on first visit. Use <strong>Sync live rate</strong> to force a fresh Mevon read and publish to the app.</p>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(() => {
    const initialSeries = @json($series);
    const range = @json($range);
    const livePoll = @json($livePoll);
    const pollSeconds = @json($pollSeconds);
    const dataUrl = @json(route('admin.virtual-cards.rate-tracker.data'));
    const ctx = document.getElementById('fxRateChart');
    if (!ctx) {
        return;
    }

    const gridColor = 'rgba(209, 213, 219, 0.6)';
    const tickColor = '#6b7280';
    const datasetDefs = [
        ['Mevon mid', 'mevon_mid', '#3C50E0', 'rgba(60, 80, 224, 0.08)'],
        ['Sell rate', 'sell_rate', '#059669', 'transparent'],
        ['Buy rate', 'buy_rate', '#7c3aed', 'transparent'],
        ['Published mid', 'published_mid', '#6b7280', 'transparent'],
    ];

    const mkDataset = (label, key, color, fill, series) => ({
        label,
        data: series.map((p) => p[key]),
        borderColor: color,
        backgroundColor: fill,
        borderWidth: key === 'mevon_mid' ? 2.5 : 1.5,
        pointRadius: series.length > 80 ? 0 : (series.length > 40 ? 1 : 2),
        pointHoverRadius: 4,
        tension: 0.35,
        fill: key === 'mevon_mid',
        spanGaps: true,
    });

    const buildChartData = (series) => ({
        labels: series.map((p) => p.label || p.t),
        datasets: datasetDefs.map(([label, key, color, fill]) => mkDataset(label, key, color, fill, series)),
    });

    let chart = null;
    if (initialSeries.length) {
        chart = new Chart(ctx, {
            type: 'line',
            data: buildChartData(initialSeries),
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: livePoll ? 300 : 750 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        labels: { color: '#374151', boxWidth: 12, font: { size: 11 } },
                    },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        titleColor: '#f9fafb',
                        bodyColor: '#e5e7eb',
                        borderColor: '#d1d5db',
                        borderWidth: 1,
                        callbacks: {
                            label: (item) => {
                                const v = item.parsed.y;
                                return v == null ? `${item.dataset.label}: —` : `${item.dataset.label}: ₦${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: { color: tickColor, maxRotation: 45, font: { size: 10 }, maxTicksLimit: 12 },
                        grid: { color: gridColor },
                    },
                    y: {
                        ticks: {
                            color: tickColor,
                            callback: (v) => '₦' + Number(v).toLocaleString(),
                        },
                        grid: { color: gridColor },
                    },
                },
            },
        });
    }

    const fmtNgn = (v) => v == null ? '—' : Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fmtMoney = (v) => v == null ? '—' : '₦' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const updateHero = (current) => {
        const set = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };
        set('fx-live-mevon', fmtNgn(current.mevon_mid));
        set('fx-published-mid', fmtNgn(current.published_mid));
        set('fx-sell-rate', fmtNgn(current.sell_rate));
        set('fx-buy-rate', fmtNgn(current.buy_rate));
        set('fx-spread', fmtNgn(current.spread));
        if (current.recorded_at) {
            const at = new Date(current.recorded_at);
            set('fx-last-snapshot', `Last snapshot just now · ${at.toLocaleString()}`);
        }
    };

    const updateStats = (stats) => {
        const root = document.getElementById('fx-period-stats');
        if (!root || !stats) return;
        const set = (key, text, className) => {
            const el = root.querySelector(`[data-stat="${key}"]`);
            if (!el) return;
            el.textContent = text;
            if (className) {
                el.className = className;
            }
        };
        set('count', Number(stats.count || 0).toLocaleString());
        set('high', fmtMoney(stats.high));
        set('low', fmtMoney(stats.low));
        set('avg', fmtMoney(stats.avg));
        set('volatility', stats.volatility != null ? Number(stats.volatility).toFixed(2) : '—');
        const net = stats.total_abs_change;
        const netClass = net == null ? 'font-mono font-semibold text-gray-500'
            : (net >= 0 ? 'font-mono font-semibold text-emerald-700' : 'font-mono font-semibold text-red-600');
        set('total_abs_change', net == null ? '—' : `${net > 0 ? '+' : ''}${Number(net).toFixed(2)}`, netClass);
    };

    const updateChart = (series) => {
        const empty = document.getElementById('fx-chart-empty');
        if (!series.length) {
            if (empty) empty.classList.remove('hidden');
            return;
        }
        if (empty) empty.classList.add('hidden');

        if (!chart) {
            chart = new Chart(ctx, {
                type: 'line',
                data: buildChartData(series),
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 300 },
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { labels: { color: '#374151', boxWidth: 12, font: { size: 11 } } },
                    },
                    scales: {
                        x: { ticks: { color: tickColor, maxRotation: 45, font: { size: 10 }, maxTicksLimit: 12 }, grid: { color: gridColor } },
                        y: { ticks: { color: tickColor, callback: (v) => '₦' + Number(v).toLocaleString() }, grid: { color: gridColor } },
                    },
                },
            });
            return;
        }

        const next = buildChartData(series);
        chart.data.labels = next.labels;
        chart.data.datasets.forEach((ds, i) => {
            ds.data = next.datasets[i].data;
            ds.pointRadius = series.length > 80 ? 0 : (series.length > 40 ? 1 : 2);
        });
        chart.update('none');
    };

    let pollTimer = null;
    let countdownTimer = null;
    let secondsLeft = pollSeconds;

    const setCountdown = () => {
        const el = document.getElementById('fx-live-countdown');
        if (el) el.textContent = `· next in ${secondsLeft}s`;
    };

    const fetchLive = async () => {
        try {
            const res = await fetch(`${dataUrl}?range=${encodeURIComponent(range)}&fresh=1`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const payload = await res.json();
            if (!payload.ok) return;
            updateHero(payload.current || {});
            updateStats(payload.stats || {});
            updateChart(payload.series || []);
            secondsLeft = payload.poll_seconds || pollSeconds;
        } catch (e) {
            // Silent — next poll retries.
        }
    };

    const stopLivePoll = () => {
        if (pollTimer) window.clearInterval(pollTimer);
        if (countdownTimer) window.clearInterval(countdownTimer);
        pollTimer = null;
        countdownTimer = null;
    };

    const startLivePoll = () => {
        if (!livePoll) return;
        stopLivePoll();
        secondsLeft = pollSeconds;
        setCountdown();
        countdownTimer = window.setInterval(() => {
            secondsLeft = Math.max(0, secondsLeft - 1);
            setCountdown();
        }, 1000);
        pollTimer = window.setInterval(() => {
            fetchLive();
            secondsLeft = pollSeconds;
        }, pollSeconds * 1000);
        window.setTimeout(fetchLive, 2000);
    };

    startLivePoll();
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopLivePoll();
        } else if (livePoll) {
            startLivePoll();
        }
    });
})();
</script>
@endpush

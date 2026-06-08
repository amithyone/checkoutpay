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
    $fmt = fn (?float $v, int $dec = 2) => $v !== null ? '₦'.number_format($v, $dec) : '—';
    $deltaClass = fn (?float $v) => $v === null ? 'text-slate-400' : ($v >= 0 ? 'text-emerald-400' : 'text-rose-400');
    $deltaPrefix = fn (?float $v) => $v === null ? '' : ($v > 0 ? '+' : '');
@endphp

<div class="space-y-6 -mx-2 sm:mx-0">
    <div class="flex flex-wrap items-center justify-between gap-4 px-2 sm:px-0">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 tracking-tight">MevonPay USD/NGN rate tracker</h2>
            <p class="text-sm text-slate-500 mt-1">Live Mevon mid, published sell/buy spreads, and historical movement</p>
        </div>
        <div class="flex flex-wrap gap-3 items-center">
            <form method="POST" action="{{ route('admin.virtual-cards.rate-tracker.refresh') }}">
                @csrf
                <input type="hidden" name="range" value="{{ $range }}">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-medium shadow-lg shadow-cyan-900/20 transition">
                    <i class="fas fa-bolt"></i> Sync live rate
                </button>
            </form>
            <a href="{{ route('admin.virtual-cards.index') }}" class="text-sm text-slate-600 hover:text-slate-900 font-medium">
                <i class="fas fa-arrow-left mr-1"></i> Card management
            </a>
            <a href="{{ route('admin.virtual-cards.stats') }}" class="text-sm text-emerald-700 hover:underline font-medium">
                <i class="fas fa-chart-pie mr-1"></i> Profit stats
            </a>
        </div>
    </div>

    {{-- Hero ticker panel --}}
    <div class="relative overflow-hidden rounded-2xl border border-slate-700/80 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 p-6 sm:p-8 shadow-2xl">
        <div class="absolute inset-0 opacity-30 pointer-events-none" style="background-image: radial-gradient(circle at 20% 20%, rgba(34,211,238,0.15), transparent 40%), radial-gradient(circle at 80% 0%, rgba(16,185,129,0.12), transparent 35%);"></div>
        <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-cyan-400/60 to-transparent"></div>

        <div class="relative grid grid-cols-1 lg:grid-cols-12 gap-6 items-end">
            <div class="lg:col-span-5">
                <p class="text-xs uppercase tracking-[0.2em] text-cyan-400/90 font-semibold mb-2">Mevon live mid · NGN per 1 USD</p>
                <p class="text-5xl sm:text-6xl font-bold text-white tabular-nums tracking-tight font-mono">
                    {{ $current['mevon_mid'] !== null ? number_format($current['mevon_mid'], 2) : '—' }}
                </p>
                <div class="flex flex-wrap gap-4 mt-4 text-sm">
                    @foreach (['24h' => '24H', '7d' => '7D', '30d' => '30D'] as $key => $label)
                        @php $c = $change[$key] ?? []; @endphp
                        <div class="rounded-lg bg-slate-800/60 border border-slate-700/50 px-3 py-2">
                            <span class="text-slate-500 text-xs block">{{ $label }}</span>
                            <span class="{{ $deltaClass($c['pct'] ?? null) }} font-mono font-semibold">
                                {{ isset($c['pct']) ? $deltaPrefix($c['pct']).number_format($c['pct'], 2).'%' : '—' }}
                            </span>
                            @if(isset($c['abs']))
                                <span class="text-slate-500 text-xs ml-1">({{ $deltaPrefix($c['abs']).number_format($c['abs'], 2) }})</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="lg:col-span-7 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-xl bg-slate-800/50 border border-slate-700/60 p-4 backdrop-blur-sm">
                    <p class="text-[10px] uppercase tracking-wider text-slate-500">Published mid</p>
                    <p class="text-xl font-bold text-white font-mono mt-1">{{ $current['published_mid'] !== null ? number_format($current['published_mid'], 2) : '—' }}</p>
                </div>
                <div class="rounded-xl bg-emerald-950/40 border border-emerald-800/40 p-4">
                    <p class="text-[10px] uppercase tracking-wider text-emerald-500/80">Sell rate</p>
                    <p class="text-xl font-bold text-emerald-300 font-mono mt-1">{{ $current['sell_rate'] !== null ? number_format($current['sell_rate'], 2) : '—' }}</p>
                    <p class="text-[10px] text-emerald-600/70 mt-1">+{{ $current['sell_markup'] !== null ? number_format($current['sell_markup'], 2) : '—' }} markup</p>
                </div>
                <div class="rounded-xl bg-violet-950/40 border border-violet-800/40 p-4">
                    <p class="text-[10px] uppercase tracking-wider text-violet-400/80">Buy rate</p>
                    <p class="text-xl font-bold text-violet-300 font-mono mt-1">{{ $current['buy_rate'] !== null ? number_format($current['buy_rate'], 2) : '—' }}</p>
                    <p class="text-[10px] text-violet-500/70 mt-1">−{{ $current['buy_discount'] !== null ? number_format($current['buy_discount'], 2) : '—' }} discount</p>
                </div>
                <div class="rounded-xl bg-amber-950/30 border border-amber-800/30 p-4">
                    <p class="text-[10px] uppercase tracking-wider text-amber-500/80">Spread</p>
                    <p class="text-xl font-bold text-amber-200 font-mono mt-1">{{ $current['spread'] !== null ? number_format($current['spread'], 2) : '—' }}</p>
                    <p class="text-[10px] text-amber-600/60 mt-1">{{ $current['source'] ?? 'unknown' }}</p>
                </div>
            </div>
        </div>

        <p class="relative text-xs text-slate-500 mt-6">
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
        <div class="xl:col-span-1 bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500 mb-3">Time range</p>
            <div class="flex flex-wrap gap-2">
                @foreach (['24h' => '24H', '7d' => '7D', '30d' => '30D', '90d' => '90D', 'all' => 'ALL'] as $key => $label)
                    <a href="{{ route('admin.virtual-cards.rate-tracker', ['range' => $key]) }}"
                       class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ $range === $key ? 'bg-slate-900 text-white shadow-md' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Snapshots</dt><dd class="font-mono font-semibold text-slate-800">{{ number_format($stats['count'] ?? 0) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Period high</dt><dd class="font-mono text-emerald-700">{{ $fmt($stats['high'] ?? null) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Period low</dt><dd class="font-mono text-rose-600">{{ $fmt($stats['low'] ?? null) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Average</dt><dd class="font-mono text-slate-800">{{ $fmt($stats['avg'] ?? null) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Volatility (σ)</dt><dd class="font-mono text-amber-700">{{ $stats['volatility'] !== null ? number_format($stats['volatility'], 2) : '—' }}</dd></div>
                <div class="flex justify-between border-t border-slate-100 pt-3"><dt class="text-slate-500">Net change</dt><dd class="font-mono font-semibold {{ $deltaClass($stats['total_abs_change'] ?? null) }}">{{ isset($stats['total_abs_change']) ? $deltaPrefix($stats['total_abs_change']).number_format($stats['total_abs_change'], 2) : '—' }}</dd></div>
            </dl>
        </div>

        <div class="xl:col-span-3 relative rounded-2xl border border-slate-800 bg-slate-950 p-5 shadow-xl overflow-hidden">
            <div class="absolute top-3 right-4 flex items-center gap-2 text-[10px] uppercase tracking-wider text-slate-500">
                <span class="inline-block w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span> Live series
            </div>
            <h3 class="text-sm font-semibold text-slate-300 mb-1">Rate movement</h3>
            <p class="text-xs text-slate-500 mb-4">Mevon mid (cyan) · Sell (green) · Buy (violet) · Published mid (slate)</p>
            <div class="h-[22rem] sm:h-96">
                <canvas id="fxRateChart"></canvas>
            </div>
            @if(empty($series))
                <div class="absolute inset-0 flex items-center justify-center bg-slate-950/80 rounded-2xl">
                    <p class="text-slate-400 text-sm">No rate history in this range. Sync live rate to start tracking.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Recent snapshots table --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">Recent snapshots</h3>
            <span class="text-xs text-slate-500">{{ count($recent) }} rows · deduped every 30 min if unchanged</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-[10px] uppercase tracking-wider text-slate-500">
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
                <tbody class="divide-y divide-slate-100 font-mono text-xs sm:text-sm">
                    @forelse($recent as $row)
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $row['recorded_at'] }}</td>
                            <td class="px-4 py-3 font-semibold text-cyan-800">{{ $row['mevon_mid'] !== null ? number_format($row['mevon_mid'], 2) : '—' }}</td>
                            <td class="px-4 py-3 text-slate-800">{{ number_format($row['published_mid'], 2) }}</td>
                            <td class="px-4 py-3 text-emerald-700">{{ $row['sell_rate'] !== null ? number_format($row['sell_rate'], 2) : '—' }}</td>
                            <td class="px-4 py-3 text-violet-700">{{ $row['buy_rate'] !== null ? number_format($row['buy_rate'], 2) : '—' }}</td>
                            <td class="px-4 py-3 {{ $deltaClass($row['change_pct']) }}">
                                @if($row['change_pct'] !== null)
                                    {{ ($row['change_pct'] > 0 ? '+' : '').number_format($row['change_pct'], 3) }}%
                                    <span class="text-slate-400">({{ ($row['change_abs'] > 0 ? '+' : '').number_format($row['change_abs'], 2) }})</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $row['source'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">No snapshots recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600 leading-relaxed">
        <p class="font-semibold text-slate-800 mb-1">How tracking works</p>
        <p>Rates are captured when MevonPay is queried live (cached fetch) and when app FX is published. Historical card transactions are backfilled once on first visit. Use <strong>Sync live rate</strong> to force a fresh Mevon read and publish to the app.</p>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(() => {
    const series = @json($series);
    const ctx = document.getElementById('fxRateChart');
    if (!ctx || !series.length) {
        return;
    }

    const labels = series.map((p) => p.label || p.t);
    const gridColor = 'rgba(148, 163, 184, 0.12)';
    const tickColor = '#94a3b8';

    const mkDataset = (label, key, color, fill) => ({
        label,
        data: series.map((p) => p[key]),
        borderColor: color,
        backgroundColor: fill,
        borderWidth: key === 'mevon_mid' ? 2.5 : 1.5,
        pointRadius: series.length > 40 ? 0 : 2,
        pointHoverRadius: 4,
        tension: 0.35,
        fill: key === 'mevon_mid',
        spanGaps: true,
    });

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                mkDataset('Mevon mid', 'mevon_mid', '#22d3ee', 'rgba(34, 211, 238, 0.08)'),
                mkDataset('Sell rate', 'sell_rate', '#34d399', 'transparent'),
                mkDataset('Buy rate', 'buy_rate', '#a78bfa', 'transparent'),
                mkDataset('Published mid', 'published_mid', '#64748b', 'transparent'),
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    labels: { color: '#cbd5e1', boxWidth: 12, font: { size: 11 } },
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.95)',
                    titleColor: '#f1f5f9',
                    bodyColor: '#e2e8f0',
                    borderColor: 'rgba(34, 211, 238, 0.3)',
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
                    ticks: { color: tickColor, maxRotation: 45, font: { size: 10 } },
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
})();
</script>
@endpush

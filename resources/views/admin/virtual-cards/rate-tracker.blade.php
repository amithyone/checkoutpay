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
                <p class="text-4xl sm:text-5xl font-bold text-gray-900 tabular-nums tracking-tight font-mono">
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
                    <p class="text-2xl font-bold text-gray-900 font-mono mt-2 tabular-nums">{{ $current['published_mid'] !== null ? number_format($current['published_mid'], 2) : '—' }}</p>
                    <p class="text-xs text-gray-500 mt-2">App mid rate</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-4 sm:p-5 shadow-sm min-h-[7.5rem] flex flex-col justify-between">
                    <p class="text-xs uppercase tracking-wider text-emerald-800 font-medium">Sell rate</p>
                    <p class="text-2xl font-bold text-emerald-800 font-mono mt-2 tabular-nums">{{ $current['sell_rate'] !== null ? number_format($current['sell_rate'], 2) : '—' }}</p>
                    <p class="text-xs text-emerald-700 mt-2">+{{ $current['sell_markup'] !== null ? number_format($current['sell_markup'], 2) : '—' }} markup</p>
                </div>
                <div class="rounded-lg border border-violet-200 bg-violet-50/60 p-4 sm:p-5 shadow-sm min-h-[7.5rem] flex flex-col justify-between">
                    <p class="text-xs uppercase tracking-wider text-violet-800 font-medium">Buy rate</p>
                    <p class="text-2xl font-bold text-violet-800 font-mono mt-2 tabular-nums">{{ $current['buy_rate'] !== null ? number_format($current['buy_rate'], 2) : '—' }}</p>
                    <p class="text-xs text-violet-700 mt-2">−{{ $current['buy_discount'] !== null ? number_format($current['buy_discount'], 2) : '—' }} discount</p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-4 sm:p-5 shadow-sm min-h-[7.5rem] flex flex-col justify-between">
                    <p class="text-xs uppercase tracking-wider text-amber-800 font-medium">Spread</p>
                    <p class="text-2xl font-bold text-amber-900 font-mono mt-2 tabular-nums">{{ $current['spread'] !== null ? number_format($current['spread'], 2) : '—' }}</p>
                    <p class="text-xs text-amber-800 mt-2 truncate" title="{{ $current['source'] ?? 'unknown' }}">{{ $current['source'] ?? 'unknown' }}</p>
                </div>
            </div>
        </div>

        <p class="text-xs text-gray-500 mt-6">
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
                @foreach (['24h' => '24H', '7d' => '7D', '30d' => '30D', '90d' => '90D', 'all' => 'ALL'] as $key => $label)
                    <a href="{{ route('admin.virtual-cards.rate-tracker', ['range' => $key]) }}"
                       class="px-3 py-1.5 rounded-lg text-sm font-medium transition border {{ $range === $key ? 'bg-indigo-100 text-indigo-900 border-indigo-300 shadow-sm' : 'bg-gray-100 text-gray-700 border-gray-200 hover:bg-gray-200' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Snapshots</dt><dd class="font-mono font-semibold text-gray-900">{{ number_format($stats['count'] ?? 0) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Period high</dt><dd class="font-mono text-emerald-700">{{ $fmt($stats['high'] ?? null) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Period low</dt><dd class="font-mono text-red-600">{{ $fmt($stats['low'] ?? null) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Average</dt><dd class="font-mono text-gray-900">{{ $fmt($stats['avg'] ?? null) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Volatility (σ)</dt><dd class="font-mono text-amber-700">{{ $stats['volatility'] !== null ? number_format($stats['volatility'], 2) : '—' }}</dd></div>
                <div class="flex justify-between border-t border-gray-100 pt-3"><dt class="text-gray-500">Net change</dt><dd class="font-mono font-semibold {{ $deltaClass($stats['total_abs_change'] ?? null) }}">{{ isset($stats['total_abs_change']) ? $deltaPrefix($stats['total_abs_change']).number_format($stats['total_abs_change'], 2) : '—' }}</dd></div>
            </dl>
        </div>

        <div class="xl:col-span-3 relative rounded-lg border border-gray-200 bg-white p-5 shadow-sm overflow-hidden">
            <div class="absolute top-3 right-4 flex items-center gap-2 text-[10px] uppercase tracking-wider text-gray-500">
                <span class="inline-block w-2 h-2 rounded-full bg-indigo-500"></span> Live series
            </div>
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Rate movement</h3>
            <p class="text-xs text-gray-500 mb-4">Mevon mid (blue) · Sell (green) · Buy (violet) · Published mid (gray)</p>
            <div class="h-80 sm:h-96">
                <canvas id="fxRateChart"></canvas>
            </div>
            @if(empty($series))
                <div class="absolute inset-0 flex items-center justify-center bg-white/90 rounded-lg">
                    <p class="text-gray-500 text-sm">No rate history in this range. Sync live rate to start tracking.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Recent snapshots table --}}
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Recent snapshots</h3>
            <span class="text-xs text-gray-500">{{ count($recent) }} rows · deduped every 30 min if unchanged</span>
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
    const series = @json($series);
    const ctx = document.getElementById('fxRateChart');
    if (!ctx || !series.length) {
        return;
    }

    const labels = series.map((p) => p.label || p.t);
    const gridColor = 'rgba(209, 213, 219, 0.6)';
    const tickColor = '#6b7280';

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
                mkDataset('Mevon mid', 'mevon_mid', '#3C50E0', 'rgba(60, 80, 224, 0.08)'),
                mkDataset('Sell rate', 'sell_rate', '#059669', 'transparent'),
                mkDataset('Buy rate', 'buy_rate', '#7c3aed', 'transparent'),
                mkDataset('Published mid', 'published_mid', '#6b7280', 'transparent'),
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
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

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
    $wallet = $tracker['wallet'] ?? [];
    $maxFxUsd = $tracker['max_fx_usd_per_op'] ?? 500;
    $liveMid = $current['mevon_mid'] ?? $current['published_mid'];
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
            @if($wallet['configured'] ?? false)
                <button type="button" onclick="openBuyUsdModal()"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium shadow-sm transition"
                        @if(!($wallet['ok'] ?? false)) disabled title="Could not load MevonPay balances" @endif>
                    <i class="fas fa-arrow-down"></i> Buy USD
                </button>
                <button type="button" onclick="openSellUsdModal()"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium shadow-sm transition"
                        @if(!($wallet['ok'] ?? false)) disabled title="Could not load MevonPay balances" @endif>
                    <i class="fas fa-arrow-up"></i> Sell USD
                </button>
            @endif
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

    @if($wallet['configured'] ?? false)
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wider text-gray-500 font-semibold">MevonPay wallet balances</p>
                    @if($wallet['ok'] ?? false)
                        <div class="flex flex-wrap gap-6 mt-2 text-sm">
                            <div>
                                <span class="text-gray-500">NGN</span>
                                <p id="fx-wallet-ngn" class="text-xl font-bold font-mono text-gray-900">
                                    {{ $wallet['naira_balance'] !== null ? '₦'.number_format($wallet['naira_balance'], 2) : '—' }}
                                </p>
                            </div>
                            <div>
                                <span class="text-gray-500">USD</span>
                                <p id="fx-wallet-usd" class="text-xl font-bold font-mono text-indigo-700">
                                    {{ $wallet['usd_balance'] !== null ? '$'.number_format($wallet['usd_balance'], 2) : '—' }}
                                </p>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-red-600 mt-2">{{ $wallet['message'] ?? 'Could not load balances.' }}</p>
                    @endif
                </div>
                <p class="text-xs text-gray-500 max-w-sm">
                    Buy USD spends NGN via Mevon <code class="text-[11px] bg-gray-100 px-1 rounded">/V1/exchange</code>.
                    Sell USD converts USD back to NGN on the same endpoint.
                    @if($maxFxUsd > 0)
                        Max per trade: <strong>${{ number_format($maxFxUsd, 2) }}</strong>.
                    @endif
                </p>
            </div>
        </div>
    @endif

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
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3 pr-28">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Rate movement</h3>
                    <p id="fx-chart-legend-hint" class="text-xs text-gray-500 mt-0.5">Price (₦) on the left · time along the bottom</p>
                </div>
                <div class="flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1">
                    <button type="button" id="fx-chart-mode-line"
                            class="fx-chart-mode-btn px-3 py-1.5 rounded-md text-xs font-semibold bg-white text-indigo-900 border border-indigo-200 shadow-sm">
                        Line
                    </button>
                    <button type="button" id="fx-chart-mode-candle"
                            class="fx-chart-mode-btn px-3 py-1.5 rounded-md text-xs font-semibold text-gray-600 hover:text-gray-900">
                        Candles
                    </button>
                </div>
            </div>
            <p id="fx-chart-series-hint" class="text-xs text-gray-500 mb-3">Mevon mid (blue) · Sell (green) · Buy (violet) · Published mid (gray)</p>
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
        <p>Rates are captured when MevonPay is queried live (cached fetch) and when app FX is published. Historical card transactions are backfilled once on first visit. Use <strong>Sync live rate</strong> to force a fresh Mevon read and publish to the app. <strong>Buy USD</strong> / <strong>Sell USD</strong> call MevonPay exchange directly to fund or withdraw merchant float.</p>
    </div>
</div>

@if($wallet['configured'] ?? false)
{{-- Buy USD modal --}}
<div id="buyUsdModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg p-5 sm:p-6 max-w-md w-full shadow-xl">
        <h3 class="text-lg font-semibold text-gray-900 mb-1">
            <i class="fas fa-arrow-down text-violet-600 mr-2"></i>Buy USD (NGN → USD)
        </h3>
        <p class="text-sm text-gray-600 mb-4">Spend NGN from your MevonPay wallet to increase USD float for virtual cards.</p>
        <form method="POST" action="{{ route('admin.virtual-cards.rate-tracker.buy-usd') }}">
            @csrf
            <input type="hidden" name="range" value="{{ $range }}">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">USD to buy <span class="text-red-500">*</span></label>
                <input type="number" name="usd_amount" step="0.01" min="0.01" @if($maxFxUsd > 0) max="{{ $maxFxUsd }}" @endif
                       required id="buy-usd-amount" value="10"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary font-mono"
                       oninput="updateBuyEstimate()">
                <p id="buy-usd-estimate" class="text-xs text-gray-500 mt-2"></p>
            </div>
            <div class="bg-violet-50 border border-violet-200 rounded-lg p-3 mb-4 text-xs text-violet-900">
                Available NGN: <strong>{{ $wallet['naira_balance'] !== null ? '₦'.number_format($wallet['naira_balance'], 2) : '—' }}</strong>
                · Live mid: <strong>{{ $liveMid !== null ? '₦'.number_format($liveMid, 2) : '—' }}</strong> / USD
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeBuyUsdModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700 font-medium">
                    Confirm buy
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Sell USD modal --}}
<div id="sellUsdModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg p-5 sm:p-6 max-w-md w-full shadow-xl">
        <h3 class="text-lg font-semibold text-gray-900 mb-1">
            <i class="fas fa-arrow-up text-emerald-600 mr-2"></i>Sell USD (USD → NGN)
        </h3>
        <p class="text-sm text-gray-600 mb-4">Convert USD from your MevonPay wallet back to NGN.</p>
        <form method="POST" action="{{ route('admin.virtual-cards.rate-tracker.sell-usd') }}">
            @csrf
            <input type="hidden" name="range" value="{{ $range }}">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">USD to sell <span class="text-red-500">*</span></label>
                <input type="number" name="usd_amount" step="0.01" min="0.01" @if($maxFxUsd > 0) max="{{ $maxFxUsd }}" @endif
                       required id="sell-usd-amount" value="10"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary font-mono"
                       oninput="updateSellEstimate()">
                <p id="sell-usd-estimate" class="text-xs text-gray-500 mt-2"></p>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3 mb-4 text-xs text-emerald-900">
                Available USD: <strong>{{ $wallet['usd_balance'] !== null ? '$'.number_format($wallet['usd_balance'], 2) : '—' }}</strong>
                · Est. rate: <strong>{{ $liveMid !== null ? '₦'.number_format($liveMid, 2) : '—' }}</strong> / USD
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeSellUsdModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-medium">
                    Confirm sell
                </button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-chart-financial@0.1.1/dist/chartjs-chart-financial.min.js"></script>
<script>
const FX_LIVE_MID = @json($liveMid);
const FX_NGN_BUFFER_PERCENT = @json((float) config('virtual_card.auto_fund_ngn_buffer_percent', 3));

function openBuyUsdModal() {
    document.getElementById('buyUsdModal')?.classList.remove('hidden');
    updateBuyEstimate();
}
function closeBuyUsdModal() {
    document.getElementById('buyUsdModal')?.classList.add('hidden');
}
function openSellUsdModal() {
    document.getElementById('sellUsdModal')?.classList.remove('hidden');
    updateSellEstimate();
}
function closeSellUsdModal() {
    document.getElementById('sellUsdModal')?.classList.add('hidden');
}
function updateBuyEstimate() {
    const input = document.getElementById('buy-usd-amount');
    const out = document.getElementById('buy-usd-estimate');
    if (!input || !out || FX_LIVE_MID == null) return;
    const usd = parseFloat(input.value) || 0;
    const ngn = Math.ceil(usd * FX_LIVE_MID * (1 + (FX_NGN_BUFFER_PERCENT / 100)));
    out.textContent = usd > 0 ? `Estimated NGN spend: ₦${ngn.toLocaleString()} (includes ${FX_NGN_BUFFER_PERCENT}% buffer)` : '';
}
function updateSellEstimate() {
    const input = document.getElementById('sell-usd-amount');
    const out = document.getElementById('sell-usd-estimate');
    if (!input || !out || FX_LIVE_MID == null) return;
    const usd = parseFloat(input.value) || 0;
    const ngn = Math.round(usd * FX_LIVE_MID * 100) / 100;
    out.textContent = usd > 0 ? `Estimated NGN received: ₦${ngn.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '';
}
['buyUsdModal', 'sellUsdModal'].forEach((id) => {
    document.getElementById(id)?.addEventListener('click', (e) => {
        if (e.target.id === id) {
            e.currentTarget.classList.add('hidden');
        }
    });
});

(() => {
    const initialSeries = @json($series);
    const range = @json($range);
    const livePoll = @json($livePoll);
    const pollSeconds = @json($pollSeconds);
    const dataUrl = @json(route('admin.virtual-cards.rate-tracker.data'));
    const canvas = document.getElementById('fxRateChart');
    if (!canvas) {
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

    let chartMode = 'line';
    let chart = null;
    let lastSeries = initialSeries;

    const parseTime = (p) => {
        if (!p?.t) return null;
        const ms = new Date(p.t).getTime();
        return Number.isFinite(ms) ? ms : null;
    };

    const timeUnitForRange = () => {
        if (range === '1h') return 'minute';
        if (['6h', '7h', '12h', '24h'].includes(range)) return 'hour';
        return 'day';
    };

    const timeScale = () => ({
        type: 'time',
        position: 'bottom',
        title: {
            display: true,
            text: 'Time',
            color: tickColor,
            font: { size: 11, weight: '600' },
        },
        ticks: {
            color: tickColor,
            maxRotation: 0,
            autoSkip: true,
            maxTicksLimit: 10,
            font: { size: 10 },
        },
        time: {
            unit: timeUnitForRange(),
            displayFormats: {
                minute: 'HH:mm',
                hour: 'MMM d, HH:mm',
                day: 'MMM d',
            },
            tooltipFormat: 'MMM d, yyyy · HH:mm:ss',
        },
        grid: { color: gridColor },
    });

    const priceScale = () => ({
        type: 'linear',
        position: 'left',
        title: {
            display: true,
            text: '₦ per 1 USD',
            color: tickColor,
            font: { size: 11, weight: '600' },
        },
        ticks: {
            color: tickColor,
            callback: (v) => '₦' + Number(v).toLocaleString(),
        },
        grid: { color: gridColor },
    });

    const mkLineDataset = (label, key, color, fill, series) => ({
        label,
        data: series.map((p) => {
            const x = parseTime(p);
            const y = p[key];
            return x != null && y != null ? { x, y } : null;
        }).filter(Boolean),
        borderColor: color,
        backgroundColor: fill,
        borderWidth: key === 'mevon_mid' ? 2.5 : 1.5,
        pointRadius: series.length > 80 ? 0 : (series.length > 40 ? 1 : 2),
        pointHoverRadius: 4,
        tension: 0.25,
        fill: key === 'mevon_mid',
        spanGaps: true,
    });

    const buildCandleData = (series) => series.map((p, i) => {
        const x = parseTime(p);
        if (x == null) return null;
        const rates = [p.mevon_mid, p.sell_rate, p.buy_rate, p.published_mid].filter((v) => v != null);
        const close = p.mevon_mid ?? p.published_mid;
        const prev = i > 0 ? (series[i - 1].mevon_mid ?? series[i - 1].published_mid) : close;
        const open = prev ?? close;
        const hi = rates.length ? Math.max(...rates) : close;
        const lo = rates.length ? Math.min(...rates) : close;
        return { x, o: open, h: hi, l: lo, c: close };
    }).filter(Boolean);

    const baseOptions = () => ({
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: livePoll ? 300 : 500 },
        interaction: { mode: chartMode === 'line' ? 'index' : 'nearest', intersect: false },
        plugins: {
            legend: {
                display: chartMode === 'line',
                labels: { color: '#374151', boxWidth: 12, font: { size: 11 } },
            },
            tooltip: {
                backgroundColor: '#1f2937',
                titleColor: '#f9fafb',
                bodyColor: '#e5e7eb',
                borderColor: '#d1d5db',
                borderWidth: 1,
                callbacks: chartMode === 'line' ? {
                    title: (items) => {
                        if (!items.length) return '';
                        const x = items[0].parsed.x;
                        return x ? new Date(x).toLocaleString() : '';
                    },
                    label: (item) => {
                        const v = item.parsed.y;
                        return v == null ? `${item.dataset.label}: —` : `${item.dataset.label}: ₦${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                    },
                } : {
                    title: (items) => {
                        if (!items.length) return '';
                        const x = items[0].parsed.x;
                        return x ? new Date(x).toLocaleString() : '';
                    },
                    label: (item) => {
                        const v = item.raw;
                        if (!v) return '';
                        const fmt = (n) => '₦' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        return [
                            `Open: ${fmt(v.o)}`,
                            `High: ${fmt(v.h)}`,
                            `Low: ${fmt(v.l)}`,
                            `Close: ${fmt(v.c)}`,
                        ];
                    },
                },
            },
        },
        scales: {
            x: timeScale(),
            y: priceScale(),
        },
    });

    const destroyChart = () => {
        if (chart) {
            chart.destroy();
            chart = null;
        }
    };

    const setModeButtons = () => {
        document.querySelectorAll('.fx-chart-mode-btn').forEach((btn) => {
            const active = btn.id === `fx-chart-mode-${chartMode}`;
            btn.classList.toggle('bg-white', active);
            btn.classList.toggle('text-indigo-900', active);
            btn.classList.toggle('border', active);
            btn.classList.toggle('border-indigo-200', active);
            btn.classList.toggle('shadow-sm', active);
            btn.classList.toggle('text-gray-600', !active);
        });
        const hint = document.getElementById('fx-chart-series-hint');
        const legend = document.getElementById('fx-chart-legend-hint');
        if (hint) {
            hint.textContent = chartMode === 'line'
                ? 'Mevon mid (blue) · Sell (green) · Buy (violet) · Published mid (gray)'
                : 'Each candle = Mevon mid OHLC per snapshot (wick spans sell/buy when present)';
        }
        if (legend) {
            legend.textContent = chartMode === 'line'
                ? 'Price (₦) on the left · time along the bottom · 4 rate lines'
                : 'Price (₦) on the left · time along the bottom · candlesticks';
        }
    };

    const renderChart = (series) => {
        const empty = document.getElementById('fx-chart-empty');
        if (!series.length) {
            destroyChart();
            if (empty) empty.classList.remove('hidden');
            return;
        }
        if (empty) empty.classList.add('hidden');
        destroyChart();

        if (chartMode === 'candle') {
            chart = new Chart(canvas, {
                type: 'candlestick',
                data: {
                    datasets: [{
                        label: 'Mevon mid',
                        data: buildCandleData(series),
                        borderColor: '#3C50E0',
                        color: {
                            up: '#059669',
                            down: '#dc2626',
                            unchanged: '#6b7280',
                        },
                    }],
                },
                options: baseOptions(),
            });
            return;
        }

        chart = new Chart(canvas, {
            type: 'line',
            data: {
                datasets: datasetDefs.map(([label, key, color, fill]) => mkLineDataset(label, key, color, fill, series)),
            },
            options: baseOptions(),
        });
    };

    setModeButtons();
    if (initialSeries.length) {
        renderChart(initialSeries);
    }

    document.getElementById('fx-chart-mode-line')?.addEventListener('click', () => {
        if (chartMode === 'line') return;
        chartMode = 'line';
        setModeButtons();
        renderChart(lastSeries);
    });
    document.getElementById('fx-chart-mode-candle')?.addEventListener('click', () => {
        if (chartMode === 'candle') return;
        chartMode = 'candle';
        setModeButtons();
        renderChart(lastSeries);
    });

    const fmtNgn = (v) => v == null ? '—' : Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fmtMoney = (v) => v == null ? '—' : '₦' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const updateHero = (current, wallet) => {
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
        if (wallet) {
            if (wallet.naira_balance != null) {
                set('fx-wallet-ngn', `₦${Number(wallet.naira_balance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
            }
            if (wallet.usd_balance != null) {
                set('fx-wallet-usd', `$${Number(wallet.usd_balance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
            }
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
        lastSeries = series;
        renderChart(series);
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
            updateHero(payload.current || {}, payload.wallet || null);
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

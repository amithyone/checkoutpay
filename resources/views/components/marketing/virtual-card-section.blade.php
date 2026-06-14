@props(['virtualCard' => []])

@if(!empty($virtualCard['enabled']))
@php
    $sellRate = $virtualCard['sell_rate'] ?? null;
    $buyRate = $virtualCard['buy_rate'] ?? null;
    $setupUsd = $virtualCard['setup_fee_usd'] ?? ($virtualCard['creation_fee_usd'] ?? 0) + ($virtualCard['initial_load_usd'] ?? 0);
    $defaultUsd = 10;
    $fundNgn = ($sellRate && $sellRate > 0) ? round($defaultUsd * $sellRate) : null;
    $setupNgn = ($sellRate && $sellRate > 0 && $setupUsd) ? round($setupUsd * $sellRate) : null;
@endphp

<section id="virtual-card" class="py-20 px-4 sm:px-6 lg:px-8 max-w-container mx-auto">
    <div class="mb-12">
        <span class="text-brand-primary font-bold text-sm flex items-center gap-2 mb-4">
            <i class="fas fa-globe"></i> New in {{ $virtualCard['brand_name'] ?? 'CheckoutNow' }}
        </span>
        <h2 class="section-heading">Dollar Virtual Card</h2>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12">
        <div class="lg:col-span-5 space-y-8">
            <p class="text-lg text-slate-600 font-medium leading-relaxed">
                Shop globally with a USD virtual card funded from your NGN wallet. Pay for Netflix, Spotify, Prime, Meta ads, and international SaaS — then withdraw unused dollars back to naira at transparent sell/buy rates with no hidden FX spread.
            </p>
            <ul class="space-y-4">
                @foreach([
                    'Instant virtual card delivery in the CheckoutNow mobile app',
                    'Fund, freeze, pause, and withdraw completely from your phone',
                    'Live USD sell/buy rates shown before you confirm every conversion',
                    'Starting USD balance included when your card is issued',
                ] as $item)
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-brand-primary mt-0.5"></i>
                        <span class="font-medium text-midnight-deep">{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="p-6 bg-surface-container-low rounded-2xl border border-slate-200/80">
                <x-marketing.app-store-badges
                    heading="Get {{ $virtualCard['brand_name'] ?? 'CheckoutNow' }} on mobile"
                    subheading="Virtual cards, NGN wallet, bills, and WhatsApp transfers — optimized for iOS and Android."
                />
            </div>
        </div>

        <div class="lg:col-span-7">
            <div class="card-marketing p-6 md:p-8">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-8">
                    <div>
                        <h3 class="text-2xl font-bold text-midnight-deep">USD rate calculator</h3>
                        <p class="text-xs text-slate-500 mt-1 flex items-center gap-2">
                            <span id="vc-live-dot" class="inline-block w-2 h-2 rounded-full bg-brand-primary animate-pulse"></span>
                            <span id="vc-live-label">Live · updates every {{ $virtualCard['poll_seconds'] ?? 60 }}s</span>
                        </p>
                    </div>
                    <div class="flex p-1 bg-surface-container-low rounded-lg w-fit" id="vc-mode-tabs">
                        <button type="button" data-vc-mode="fund" class="vc-mode px-4 py-1.5 rounded-md text-sm font-bold bg-brand-primary text-white">Fund card</button>
                        <button type="button" data-vc-mode="withdraw" class="vc-mode px-4 py-1.5 text-slate-500 text-sm font-bold">Withdraw</button>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="space-y-2">
                        <label for="vc-usd" class="text-sm font-bold text-slate-500">USD amount</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold">$</span>
                            <input type="number" id="vc-usd" value="{{ $defaultUsd }}" min="1" step="0.01"
                                class="w-full pl-9 pr-4 py-4 rounded-xl border border-slate-200 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 outline-none font-bold text-midnight-deep">
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label for="vc-ngn" class="text-sm font-bold text-slate-500">NGN amount</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold">₦</span>
                            <input type="text" id="vc-ngn" readonly
                                class="w-full pl-9 pr-4 py-4 rounded-xl border border-slate-200 bg-surface-container-low text-midnight-deep font-bold"
                                value="{{ $fundNgn ? number_format($fundNgn) : '—' }}">
                        </div>
                    </div>
                </div>
                <div class="bg-brand-primary/5 rounded-2xl p-6 border border-brand-primary/10 mb-8">
                    <p class="text-sm text-slate-600 mb-2">Rate used: <span class="font-bold text-midnight-deep" id="vc-rate-label">{{ $virtualCard['sell_rate_label'] ?? '—' }} per $1</span></p>
                    <p class="text-sm text-midnight-deep" id="vc-summary">
                        @if($fundNgn)
                            Funding ${{ number_format($defaultUsd, 2) }} on your card costs about <span class="font-bold">₦{{ number_format($fundNgn) }}</span> from your NGN wallet.
                        @endif
                    </p>
                    @if($setupUsd && $setupNgn)
                        <p class="text-xs text-slate-500 mt-2">Card setup today: <span class="font-bold">${{ number_format($setupUsd, 2) }}</span> (≈ ₦{{ number_format($setupNgn) }} at the fund rate)</p>
                    @endif
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="p-4 rounded-xl bg-surface-container-low text-center">
                        <p class="text-[10px] uppercase font-bold text-slate-500 mb-1">Fund Card</p>
                        <p id="vc-sell-rate-display" class="text-xl font-black text-midnight-deep">{{ $virtualCard['sell_rate_label'] ?? '—' }}</p>
                        <p class="text-[10px] text-slate-500">per $1 from NGN</p>
                    </div>
                    <div class="p-4 rounded-xl bg-surface-container-low text-center">
                        <p class="text-[10px] uppercase font-bold text-slate-500 mb-1">Withdraw</p>
                        <p id="vc-buy-rate-display" class="text-xl font-black text-midnight-deep">{{ $virtualCard['buy_rate_label'] ?? '—' }}</p>
                        <p class="text-[10px] text-slate-500">per $1 to NGN</p>
                    </div>
                    <div class="p-4 rounded-xl bg-surface-container-low text-center">
                        <p class="text-[10px] uppercase font-bold text-slate-500 mb-1">Card Setup</p>
                        <p class="text-xl font-black text-midnight-deep">${{ number_format($setupUsd, 2) }}</p>
                        <p class="text-[10px] text-slate-500">incl. ${{ number_format($virtualCard['initial_load_usd'] ?? 0, 2) }} load</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script>
(function() {
    var sellRate = {{ json_encode($sellRate) }};
    var buyRate = {{ json_encode($buyRate) }};
    var mode = 'fund';
    var usdInput = document.getElementById('vc-usd');
    var ngnInput = document.getElementById('vc-ngn');
    var rateLabel = document.getElementById('vc-rate-label');
    var summary = document.getElementById('vc-summary');
    var sellDisplay = document.getElementById('vc-sell-rate-display');
    var buyDisplay = document.getElementById('vc-buy-rate-display');
    var dataUrl = @json($virtualCard['fx_rates_url'] ?? null);
    var pollSeconds = {{ (int) ($virtualCard['poll_seconds'] ?? 60) }};
    if (!usdInput || !ngnInput) return;

    function fmt(n) { return Math.round(n).toLocaleString('en-NG'); }
    function fmtRate(n) { return n == null ? '—' : '₦' + fmt(n); }

    function update() {
        var usd = parseFloat(usdInput.value) || 0;
        var rate = mode === 'fund' ? sellRate : buyRate;
        if (!rate || rate <= 0) return;
        var ngn = Math.round(usd * rate);
        ngnInput.value = fmt(ngn);
        rateLabel.textContent = '₦' + fmt(rate) + ' per $1';
        summary.innerHTML = (mode === 'fund' ? 'Funding' : 'Withdrawing') + ' $' + usd.toFixed(2) + ' costs about <span class="font-bold">₦' + fmt(ngn) + '</span> ' + (mode === 'fund' ? 'from' : 'to') + ' your NGN wallet.';
    }

    function applyRates(payload) {
        if (!payload || !payload.ok) return;
        if (payload.sell_rate != null) sellRate = payload.sell_rate;
        if (payload.buy_rate != null) buyRate = payload.buy_rate;
        if (sellDisplay && sellRate != null) sellDisplay.textContent = fmtRate(sellRate);
        if (buyDisplay && buyRate != null) buyDisplay.textContent = fmtRate(buyRate);
        update();
    }

    function pollRates() {
        if (!dataUrl) return;
        fetch(dataUrl + '?fresh=1', { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.ok ? r.json() : null; })
            .then(applyRates)
            .catch(function() {});
    }

    document.querySelectorAll('.vc-mode').forEach(function(btn) {
        btn.addEventListener('click', function() {
            mode = btn.getAttribute('data-vc-mode');
            document.querySelectorAll('.vc-mode').forEach(function(b) {
                var active = b.getAttribute('data-vc-mode') === mode;
                b.classList.toggle('bg-brand-primary', active);
                b.classList.toggle('text-white', active);
                b.classList.toggle('text-slate-500', !active);
            });
            update();
        });
    });
    usdInput.addEventListener('input', update);
    update();

    if (dataUrl && pollSeconds > 0) {
        pollRates();
        setInterval(pollRates, pollSeconds * 1000);
    }
})();
</script>
@endpush
@endif

@props([
    'virtualCard' => [],
    'compact' => false,
])
@php
    $card = is_array($virtualCard) ? $virtualCard : [];
    $enabled = (bool) ($card['enabled'] ?? false);
    $sellRate = $card['sell_rate'] ?? null;
    $buyRate = $card['buy_rate'] ?? null;
@endphp
@if ($enabled && $sellRate !== null && $buyRate !== null)
<div
    class="dollar-card-calculator bg-white rounded-2xl border border-violet-200 shadow-sm p-5 sm:p-6 {{ $compact ? '' : 'lg:p-8' }}"
    data-sell-rate="{{ $sellRate }}"
    data-buy-rate="{{ $buyRate }}"
    data-setup-usd="{{ $card['setup_fee_usd'] ?? 0 }}"
>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <div>
            <h3 class="text-lg sm:text-xl font-bold text-gray-900">USD rate calculator</h3>
            <p class="text-sm text-gray-600 mt-1">Check what you pay to fund a card or receive when you withdraw to NGN.</p>
        </div>
        <div class="inline-flex rounded-lg border border-gray-200 p-1 bg-gray-50 text-sm font-medium shrink-0">
            <button type="button" data-mode="fund" class="dollar-card-calc-mode px-3 py-1.5 rounded-md bg-violet-600 text-white">Fund card</button>
            <button type="button" data-mode="withdraw" class="dollar-card-calc-mode px-3 py-1.5 rounded-md text-gray-700 hover:text-gray-900">Withdraw</button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <label class="block">
            <span class="text-sm font-medium text-gray-700">USD amount</span>
            <div class="mt-1 relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                <input
                    type="number"
                    min="0"
                    step="0.01"
                    value="10"
                    class="dollar-card-calc-usd w-full pl-8 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                >
            </div>
        </label>
        <label class="block">
            <span class="text-sm font-medium text-gray-700">NGN amount</span>
            <div class="mt-1 relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">₦</span>
                <input
                    type="number"
                    min="0"
                    step="1"
                    class="dollar-card-calc-ngn w-full pl-8 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                >
            </div>
        </label>
    </div>

    <div class="rounded-xl bg-violet-50 border border-violet-100 p-4 text-sm text-gray-700 space-y-2">
        <p>
            <span class="font-semibold text-gray-900">Rate used:</span>
            <span class="dollar-card-calc-rate-label">{{ $card['sell_rate_label'] ?? '' }}</span> per $1
        </p>
        <p class="dollar-card-calc-summary"></p>
        <p class="text-xs text-gray-500">
            Card setup today: <strong>${{ number_format((float) ($card['setup_fee_usd'] ?? 0), 2) }}</strong>
            @if (! empty($card['setup_fee_ngn_label']))
                (≈ {{ $card['setup_fee_ngn_label'] }} at the fund rate)
            @endif
            — includes ${{ number_format((float) ($card['initial_load_usd'] ?? 0), 2) }} starting balance.
        </p>
    </div>
</div>

@once
<script>
(function () {
    function formatNgn(value) {
        if (!isFinite(value)) return '—';
        return '₦' + Math.round(value).toLocaleString('en-NG');
    }

    function formatUsd(value) {
        if (!isFinite(value)) return '—';
        return '$' + Number(value).toFixed(2);
    }

    document.querySelectorAll('.dollar-card-calculator').forEach(function (root) {
        var sellRate = parseFloat(root.dataset.sellRate || '0');
        var buyRate = parseFloat(root.dataset.buyRate || '0');
        var mode = 'fund';
        var usdInput = root.querySelector('.dollar-card-calc-usd');
        var ngnInput = root.querySelector('.dollar-card-calc-ngn');
        var rateLabel = root.querySelector('.dollar-card-calc-rate-label');
        var summary = root.querySelector('.dollar-card-calc-summary');
        var modeButtons = root.querySelectorAll('.dollar-card-calc-mode');
        var updating = false;

        function activeRate() {
            return mode === 'fund' ? sellRate : buyRate;
        }

        function setMode(nextMode) {
            mode = nextMode;
            modeButtons.forEach(function (btn) {
                var active = btn.dataset.mode === mode;
                btn.classList.toggle('bg-violet-600', active);
                btn.classList.toggle('text-white', active);
                btn.classList.toggle('text-gray-700', !active);
            });
            rateLabel.textContent = formatNgn(activeRate());
            syncFromUsd();
        }

        function syncFromUsd() {
            if (updating) return;
            updating = true;
            var usd = parseFloat(usdInput.value || '0');
            var rate = activeRate();
            ngnInput.value = usd > 0 && rate > 0 ? Math.round(usd * rate) : '';
            renderSummary(usd, usd * rate);
            updating = false;
        }

        function syncFromNgn() {
            if (updating) return;
            updating = true;
            var ngn = parseFloat(ngnInput.value || '0');
            var rate = activeRate();
            var usd = ngn > 0 && rate > 0 ? ngn / rate : 0;
            usdInput.value = usd > 0 ? usd.toFixed(2) : '';
            renderSummary(usd, ngn);
            updating = false;
        }

        function renderSummary(usd, ngn) {
            if (!usd || !ngn) {
                summary.textContent = 'Enter an amount to see the conversion.';
                return;
            }
            if (mode === 'fund') {
                summary.textContent = 'Funding ' + formatUsd(usd) + ' on your card costs about ' + formatNgn(ngn) + ' from your NGN wallet.';
            } else {
                summary.textContent = 'Withdrawing ' + formatUsd(usd) + ' from your card pays about ' + formatNgn(ngn) + ' into your NGN wallet.';
            }
        }

        modeButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setMode(btn.dataset.mode);
            });
        });

        usdInput.addEventListener('input', syncFromUsd);
        ngnInput.addEventListener('input', syncFromNgn);

        setMode('fund');
    });
})();
</script>
@endonce
@endif

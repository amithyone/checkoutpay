@props([
    'virtualCard' => [],
    'showCalculator' => true,
    'id' => 'dollar-virtual-card',
])
@php
    $card = is_array($virtualCard) ? $virtualCard : [];
    $enabled = (bool) ($card['enabled'] ?? false);
@endphp
@if ($enabled)
<section id="{{ $id }}" class="py-12 sm:py-16 md:py-20 bg-gradient-to-br from-violet-50 via-white to-indigo-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-start">
            <div>
                <div class="inline-flex items-center gap-2 bg-violet-100 text-violet-800 px-3 py-1 rounded-full text-xs font-semibold mb-4">
                    <i class="fas fa-credit-card"></i> New in {{ $card['brand_name'] ?? 'CheckoutNow' }}
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">Dollar Virtual Card</h2>
                <p class="text-base sm:text-lg text-gray-600 mb-6 max-w-xl">
                    Shop globally with a USD virtual card funded from your NGN wallet. Pay for subscriptions, ads, and international checkouts — then withdraw unused dollars back to naira when you are done.
                </p>

                <ul class="space-y-3 text-sm sm:text-base text-gray-700 mb-8">
                    <li class="flex items-start"><i class="fas fa-check-circle text-violet-500 mt-1 mr-3"></i> Instant card in the {{ $card['brand_name'] ?? 'CheckoutNow' }} app</li>
                    <li class="flex items-start"><i class="fas fa-check-circle text-violet-500 mt-1 mr-3"></i> Fund, freeze, and withdraw from your phone</li>
                    <li class="flex items-start"><i class="fas fa-check-circle text-violet-500 mt-1 mr-3"></i> Transparent USD sell/buy rates — no hidden FX spread</li>
                    <li class="flex items-start"><i class="fas fa-check-circle text-violet-500 mt-1 mr-3"></i> Starting balance included when your card is issued</li>
                </ul>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-8">
                    <div class="rounded-xl bg-white border border-violet-100 p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Fund card</p>
                        <p class="text-xl font-bold text-gray-900">{{ $card['sell_rate_label'] ?? '—' }}</p>
                        <p class="text-xs text-gray-500 mt-1">per $1 from NGN wallet</p>
                    </div>
                    <div class="rounded-xl bg-white border border-violet-100 p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Withdraw</p>
                        <p class="text-xl font-bold text-gray-900">{{ $card['buy_rate_label'] ?? '—' }}</p>
                        <p class="text-xs text-gray-500 mt-1">per $1 to NGN wallet</p>
                    </div>
                    <div class="rounded-xl bg-white border border-violet-100 p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Card setup</p>
                        <p class="text-xl font-bold text-gray-900">${{ number_format((float) ($card['setup_fee_usd'] ?? 0), 2) }}</p>
                        <p class="text-xs text-gray-500 mt-1">incl. ${{ number_format((float) ($card['initial_load_usd'] ?? 0), 2) }} load</p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row flex-wrap gap-3">
                    <x-checkoutnow-apk-download
                        label="Get the app"
                        class="inline-flex items-center justify-center px-6 py-3 bg-violet-600 text-white rounded-lg hover:bg-violet-700 font-semibold shadow-sm"
                    />
                    <a href="{{ $card['app_url'] ?? \App\Support\CheckoutNowApp::webUrl() }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="inline-flex items-center justify-center px-6 py-3 border border-violet-300 text-violet-800 rounded-lg hover:bg-violet-50 font-semibold">
                        Open web app <i class="fas fa-external-link-alt ml-2"></i>
                    </a>
                </div>
            </div>

            @if ($showCalculator)
                <x-dollar-card-calculator :virtual-card="$card" />
            @endif
        </div>
    </div>
</section>
@endif

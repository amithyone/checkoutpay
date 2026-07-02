@php
    $activeProvider = $activeProvider ?? 'mevonpay';
    $preserve = array_filter(request()->only(['status', 'q', 'from', 'to', 'event', 'level', 'request_id']));
@endphp
<div class="border-b border-gray-200">
    <nav class="-mb-px flex flex-wrap gap-4" aria-label="Card provider">
        <a href="{{ route($routeName, array_merge($preserve, ['provider' => 'mevonpay'])) }}"
            class="inline-flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-medium {{ $activeProvider === 'mevonpay' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
            <i class="fas fa-credit-card text-indigo-600"></i>
            MevonPay
            @if(($providerCounts['mevonpay'] ?? null) !== null)
                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-800">{{ number_format($providerCounts['mevonpay']) }}</span>
            @endif
        </a>
        <a href="{{ route($routeName, array_merge($preserve, ['provider' => 'cashwyre'])) }}"
            class="inline-flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-medium {{ $activeProvider === 'cashwyre' ? 'border-orange-600 text-orange-700' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
            <i class="fas fa-credit-card text-orange-600"></i>
            Cashwyre
            @if(($providerCounts['cashwyre'] ?? null) !== null)
                <span class="rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-800">{{ number_format($providerCounts['cashwyre']) }}</span>
            @endif
        </a>
    </nav>
</div>

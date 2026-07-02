@php
    $providerKey = $provider ?? ($card->provider ?? 'mevonpay');
    $isCashwyre = $providerKey === 'cashwyre';
    $label = $isCashwyre ? 'Cashwyre' : 'MevonPay';
    $classes = $isCashwyre
        ? 'bg-orange-100 text-orange-800 border-orange-200'
        : 'bg-indigo-100 text-indigo-800 border-indigo-200';
@endphp
<span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $classes }}">{{ $label }}</span>

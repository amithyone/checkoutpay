@php
    $label = $withdrawal->sourceLabel();
@endphp
@if($label)
    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full {{ $withdrawal->isFromPayoutApi() ? 'bg-violet-100 text-violet-800' : 'bg-gray-100 text-gray-700' }}"
        title="{{ $withdrawal->isFromPayoutApi() ? 'Started from POST /api/v1/withdrawal (merchant payout API)' : $label }}">
        @if($withdrawal->isFromPayoutApi())
            <i class="fas fa-plug mr-1"></i>
        @endif
        {{ $label }}
    </span>
@endif

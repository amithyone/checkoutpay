@props(['loan'])

@php
    $next = $loan->status === \App\Models\BusinessLoan::STATUS_ACTIVE ? $loan->nextCollectionSummary() : null;
@endphp
@if($next)
    <div {{ $attributes->merge(['class' => 'mt-1 text-xs text-gray-600']) }}>
        <span class="font-medium text-gray-800">Next collection:</span>
        ₦{{ number_format($next['amount'], 2) }}
        <span class="text-gray-500">due {{ $next['due_at']->format('M j, Y') }}</span>
        <span class="text-gray-500">
            · Installment {{ $next['sequence'] }} of {{ $next['total_schedules'] }}
            · {{ $next['cadence_label'] }}
            · {{ $next['term_days'] }}d offer term
            @if($loan->offer && $loan->offer->repayment_type === \App\Models\BusinessLendingOffer::REPAYMENT_SPLIT)
                ({{ $next['split_installments'] }} equal split{{ $next['split_installments'] === 1 ? '' : 's' }})
            @endif
        </span>
    </div>
@elseif($loan->status === \App\Models\BusinessLoan::STATUS_ACTIVE && $loan->schedules->isNotEmpty() && $next === null)
    <div {{ $attributes->merge(['class' => 'mt-1 text-xs text-emerald-700']) }}>All installments collected.</div>
@endif

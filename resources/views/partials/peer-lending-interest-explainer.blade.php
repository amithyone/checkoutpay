{{-- Shared wording: interest = % of principal for the offer term; not annualised. @param string $variant panel | inline | field-help | term-field-help | offer-detail --}}
@php
    $variant = $variant ?? 'panel';
@endphp
@if($variant === 'panel')
    <div class="mb-4 p-3 bg-slate-50 border border-slate-200 rounded-lg">
        <p class="text-xs font-semibold text-slate-800 mb-1">How peer loan interest works</p>
        <p class="text-xs text-slate-700 leading-relaxed">The quoted percentage is <strong>of the loan principal</strong> for that offer’s whole term. It is <strong>not annualised</strong>. Term length only sets <strong>when</strong> repayments are due and how installments are spaced—it does not scale the interest amount up or down by the number of days.</p>
    </div>
@elseif($variant === 'inline')
    <p class="text-xs text-gray-600 {{ $class ?? 'mb-4' }}">Interest on each offer is a percentage of principal for that term (not annualised); the term sets due dates and installments only.</p>
@elseif($variant === 'field-help')
    <p class="text-xs text-gray-500 mt-0.5">Borrower repays principal plus this percentage of principal. Term length sets due date(s) and installment spacing, not the interest amount.</p>
@elseif($variant === 'term-field-help')
    <p class="text-xs text-gray-500 mt-1">Used for repayment due dates and installment spacing only—not to calculate interest.</p>
@elseif($variant === 'offer-detail')
    <p class="text-sm text-gray-600">Interest: <span class="font-medium">{{ number_format($offer->interest_rate_percent, 2) }}%</span> of loan principal for this {{ $offer->term_days }}-day term (not annualised).</p>
@endif

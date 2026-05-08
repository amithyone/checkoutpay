@extends('layouts.business')

@section('title', 'Edit lending offer')
@section('page-title', 'Edit lending offer')

@section('content')
<div class="max-w-lg mx-auto bg-white rounded-lg border border-gray-200 p-6">
    <p class="text-sm text-gray-600 mb-4">Balance available: <strong>₦{{ number_format($business->balance, 2) }}</strong></p>

    @if(($lenderCaps['reserve'] ?? 0) > 0 || ($lenderCaps['max_amount'] ?? 0) < (float) $business->balance || !empty($lenderCaps['conditions']) || ($lenderCaps['max_interest'] ?? 100) < 100 || ($lenderCaps['min_term'] ?? 7) > 7 || ($lenderCaps['max_term'] ?? 730) < 730)
        <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-900 space-y-1">
            <p class="font-semibold">Administrator lender rules</p>
            <ul class="list-disc list-inside text-xs space-y-0.5">
                <li>Max you can offer now: <strong>₦{{ number_format($lenderCaps['max_amount'], 2) }}</strong></li>
                @if(($lenderCaps['reserve'] ?? 0) > 0)
                    <li>Minimum balance to keep: <strong>₦{{ number_format($lenderCaps['reserve'], 2) }}</strong></li>
                @endif
                <li>Interest cap: <strong>{{ number_format($lenderCaps['max_interest'], 2) }}%</strong></li>
                <li>Term must be between <strong>{{ $lenderCaps['min_term'] }}</strong> and <strong>{{ $lenderCaps['max_term'] }}</strong> days</li>
            </ul>
            @if(!empty($lenderCaps['conditions']))
                <p class="text-xs mt-2 whitespace-pre-wrap border-t border-blue-200 pt-2">{{ $lenderCaps['conditions'] }}</p>
            @endif
        </div>
    @endif

    @if($offer->status === \App\Models\BusinessLendingOffer::STATUS_REJECTED)
        <div class="mb-4 p-3 bg-amber-50 border border-amber-200 text-amber-900 text-sm rounded-lg">
            This offer was rejected. Saving your changes will resubmit it for admin approval.
        </div>
    @endif

    <form id="lendingOfferForm" method="POST" action="{{ route('business.lending-offers.update', $offer) }}" class="space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700">Amount (₦)</label>
            <input type="number" name="amount" step="0.01" min="0.01" max="{{ number_format($lenderCaps['max_amount'], 2, '.', '') }}" value="{{ old('amount', $offer->amount) }}" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            <p class="text-xs text-gray-500 mt-1">Minimum offer amount is enforced on submit (typically ₦1,000 unless your cap is lower).</p>
            @error('amount')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Interest rate (% flat over term)</label>
            <input type="number" name="interest_rate_percent" step="0.01" min="0" max="{{ number_format($lenderCaps['max_interest'], 4, '.', '') }}" value="{{ old('interest_rate_percent', $offer->interest_rate_percent) }}" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            @error('interest_rate_percent')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Term (days)</label>
            <input type="number" name="term_days" min="1" max="{{ $lenderCaps['max_term'] }}" value="{{ old('term_days', $offer->term_days) }}" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            <p class="text-xs text-gray-500 mt-1">Term must be between {{ $lenderCaps['min_term'] }} and {{ $lenderCaps['max_term'] }} days (checked when you submit).</p>
            @error('term_days')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Repayment</label>
            <select name="repayment_type" id="repayment_type" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" onchange="toggleFrequencyField()">
                <option value="lump" @selected(old('repayment_type', $offer->repayment_type)==='lump')>One-time at end</option>
                <option value="split" @selected(old('repayment_type', $offer->repayment_type)==='split')>Split installments</option>
            </select>
        </div>
        <div id="repayment_frequency_wrap" class="{{ old('repayment_type', $offer->repayment_type) === 'split' ? '' : 'hidden' }}">
            <label class="block text-sm font-medium text-gray-700">Repayment frequency</label>
            <select name="repayment_frequency" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="daily" @selected(old('repayment_frequency', $offer->repayment_frequency)==='daily')>Daily</option>
                <option value="weekly" @selected(old('repayment_frequency', $offer->repayment_frequency ?? 'weekly')==='weekly')>Weekly</option>
                <option value="monthly" @selected(old('repayment_frequency', $offer->repayment_frequency)==='monthly')>Monthly (every 30 days)</option>
            </select>
            <p class="text-xs text-gray-500 mt-1">Equal installments are auto-scheduled across the loan term.</p>
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="hidden" name="list_publicly" value="0">
            <input type="checkbox" name="list_publicly" value="1" class="rounded border-gray-300" @checked(old('list_publicly', $offer->list_publicly ? '1' : '0') == '1')>
            List on public marketplace
        </label>
        <div class="flex gap-2">
            <a href="{{ route('business.lending-offers.index') }}" class="flex-1 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium text-center">Cancel</a>
            <button type="submit" id="lendingOfferSubmit" class="flex-1 py-2.5 bg-primary text-white rounded-lg text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed">Save changes</button>
        </div>
    </form>
</div>
<script>
function toggleFrequencyField() {
    const sel = document.getElementById('repayment_type');
    const wrap = document.getElementById('repayment_frequency_wrap');
    if (!sel || !wrap) return;
    if (sel.value === 'split') {
        wrap.classList.remove('hidden');
    } else {
        wrap.classList.add('hidden');
    }
}

(function () {
    toggleFrequencyField();
    const form = document.getElementById('lendingOfferForm');
    const btn = document.getElementById('lendingOfferSubmit');
    if (!form || !btn) return;
    form.addEventListener('submit', function () {
        if (btn.dataset.submitting === '1') {
            return;
        }
        btn.dataset.submitting = '1';
        btn.disabled = true;
        btn.textContent = 'Saving…';
    });
})();
</script>
@endsection

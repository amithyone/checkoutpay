@extends('layouts.admin')

@section('title', 'Edit loan')
@section('page-title', 'Peer lending — edit loan')

@section('content')
@php
    $currentType = old('repayment_type', $loan->admin_repayment_type ?? $loan->offer->repayment_type);
    $currentFreq = old('repayment_frequency', $loan->admin_repayment_frequency ?? ($loan->offer->repayment_frequency ?? \App\Models\BusinessLendingOffer::FREQUENCY_WEEKLY));
@endphp
<div class="max-w-xl mx-auto">
    <a href="{{ route('admin.peer-lending.loans.index') }}" class="text-sm text-primary hover:underline mb-4 inline-block">&larr; Back to loans</a>
    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
        <p class="text-sm text-gray-600 mb-1">Borrower: <strong>{{ $loan->borrower->name }}</strong></p>
        <p class="text-sm text-gray-600 mb-1">Lender: <strong>{{ $loan->offer->lender->name }}</strong></p>
        <p class="text-sm text-gray-600 mb-4">Principal ₦{{ number_format($loan->principal, 2) }} → repay ₦{{ number_format($loan->total_repayment, 2) }} · Offer term {{ $loan->offer->term_days }} days</p>
        @if($loan->status === \App\Models\BusinessLoan::STATUS_ACTIVE)
            @if($loan->repaidAmount() >= 0.01)
                <div class="mb-4 p-3 bg-amber-50 border border-amber-200 text-amber-900 text-sm rounded-lg">
                    This loan already has repayments. Saving will replace schedule rows: amounts collected so far are rolled into <strong>one paid</strong> line, and the <strong>remaining balance</strong> is rescheduled with your new lump/split from today through the original contract end date. Ledger history is unchanged.
                </div>
            @else
                <div class="mb-4 p-3 bg-amber-50 border border-amber-200 text-amber-900 text-sm rounded-lg">
                    This loan is disbursed. Saving will <strong>replace all schedule rows</strong> using the settings below.
                </div>
            @endif
        @endif
        @include('partials.peer-lending-interest-explainer', ['variant' => 'panel'])
        <form method="POST" action="{{ route('admin.peer-lending.loans.repayment.update', $loan) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium text-gray-700">Repayment for this loan</label>
                <p class="text-xs text-gray-500 mt-1 mb-2">Overrides the marketplace offer for <strong>this borrower only</strong>. Collection cron uses these values.</p>
                <select name="repayment_type" id="repayment_type" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" onchange="toggleFrequencyField()">
                    <option value="lump" @selected($currentType === 'lump')>Lump sum at end of term — one full repayment on the last day</option>
                    <option value="split" @selected($currentType === 'split')>Split — equal installments (choose rhythm below)</option>
                </select>
            </div>
            <div id="repayment_frequency_wrap" class="{{ $currentType === 'split' ? '' : 'hidden' }}">
                <label class="block text-sm font-medium text-gray-700">Installment rhythm</label>
                <select name="repayment_frequency" id="repayment_frequency_select" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="daily" @selected($currentFreq === 'daily')>Daily</option>
                    <option value="weekly" @selected($currentFreq === 'weekly')>Weekly</option>
                    <option value="monthly" @selected($currentFreq === 'monthly')>Monthly (30-day steps)</option>
                </select>
            </div>
            <p id="repayment_schedule_preview" class="text-xs text-blue-900 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2 hidden" role="status"></p>
            @error('repayment_type')<p class="text-red-600 text-xs">{{ $message }}</p>@enderror
            @error('repayment_frequency')<p class="text-red-600 text-xs">{{ $message }}</p>@enderror
            <div class="flex gap-2 pt-2">
                <a href="{{ route('admin.peer-lending.loans.index') }}" class="flex-1 py-2 text-center border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="flex-1 py-2 bg-primary text-white rounded-lg text-sm font-medium">Save repayment</button>
            </div>
        </form>
    </div>
</div>
<script>
function installmentCount(termDays, repaymentType, freq) {
    if (repaymentType !== 'split' || !Number.isFinite(termDays) || termDays < 1) return null;
    let step = 7;
    if (freq === 'daily') step = 1;
    else if (freq === 'monthly') step = 30;
    return Math.max(1, Math.ceil(termDays / step));
}
function updateRepaymentPreview() {
    const typeEl = document.getElementById('repayment_type');
    const freqEl = document.getElementById('repayment_frequency_select');
    const out = document.getElementById('repayment_schedule_preview');
    if (!typeEl || !out) return;
    const term = {{ (int) $loan->offer->term_days }};
    const type = typeEl.value;
    const freq = freqEl && !freqEl.disabled ? freqEl.value : 'weekly';
    if (type === 'lump') {
        out.textContent = 'One installment for the full amount on day ' + term + ' after disbursement.';
        out.classList.remove('hidden');
        return;
    }
    const n = installmentCount(term, type, freq);
    if (!n) { out.classList.add('hidden'); return; }
    out.textContent = n + ' equal installment(s) (total repayment ÷ ' + n + ') over ' + term + ' days.';
    out.classList.remove('hidden');
}
function toggleFrequencyField() {
    const sel = document.getElementById('repayment_type');
    const wrap = document.getElementById('repayment_frequency_wrap');
    const freq = document.getElementById('repayment_frequency_select');
    if (!sel || !wrap || !freq) return;
    if (sel.value === 'split') {
        wrap.classList.remove('hidden');
        freq.disabled = false;
    } else {
        wrap.classList.add('hidden');
        freq.disabled = true;
    }
    updateRepaymentPreview();
}
document.addEventListener('DOMContentLoaded', function () {
    toggleFrequencyField();
    const freqEl = document.getElementById('repayment_frequency_select');
    if (freqEl) freqEl.addEventListener('change', updateRepaymentPreview);
});
</script>
@endsection

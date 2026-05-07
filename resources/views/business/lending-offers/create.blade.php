@extends('layouts.business')

@section('title', 'New lending offer')
@section('page-title', 'New lending offer')

@section('content')
<div class="max-w-lg mx-auto bg-white rounded-lg border border-gray-200 p-6">
    <p class="text-sm text-gray-600 mb-4">Balance available: <strong>₦{{ number_format($business->balance, 2) }}</strong></p>
    <form method="POST" action="{{ route('business.lending-offers.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700">Amount (₦)</label>
            <input type="number" name="amount" step="0.01" min="1000" value="{{ old('amount') }}" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            @error('amount')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Interest rate (% flat over term)</label>
            <input type="number" name="interest_rate_percent" step="0.01" min="0" max="100" value="{{ old('interest_rate_percent') }}" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Term (days)</label>
            <input type="number" name="term_days" min="7" max="730" value="{{ old('term_days', 30) }}" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Repayment</label>
            <select name="repayment_type" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="lump" @selected(old('repayment_type')==='lump')>One-time at end</option>
                <option value="split" @selected(old('repayment_type','split')==='split')>Split (weekly)</option>
            </select>
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="hidden" name="list_publicly" value="0">
            <input type="checkbox" name="list_publicly" value="1" class="rounded border-gray-300" @checked(old('list_publicly', '1') == '1')>
            List on public marketplace
        </label>
        <button type="submit" class="w-full py-2.5 bg-primary text-white rounded-lg text-sm font-medium">Submit for approval</button>
    </form>
</div>
@endsection

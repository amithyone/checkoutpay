@extends('layouts.business')

@section('title', 'Create payment link')
@section('page-title', 'Create payment link')

@section('content')
<div class="max-w-2xl pb-20 lg:pb-0">
    <a href="{{ route('business.payment-links.index') }}" class="text-sm text-gray-600 hover:text-gray-900 mb-4 inline-block"><i class="fas fa-arrow-left mr-1"></i> Back</a>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">New payment link</h2>
        <p class="text-sm text-gray-600 mb-6">Your client opens a dedicated pay page and transfers to a CheckoutPay account. Money is matched like an invoice.</p>

        <form method="POST" action="{{ route('business.payment-links.store') }}" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="255" placeholder="e.g. Website deposit, School fees"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg @error('title') border-red-400 @enderror">
                @error('title')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                <textarea name="description" rows="3" maxlength="2000" class="w-full px-3 py-2 border border-gray-300 rounded-lg">{{ old('description') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer">
                        <input type="radio" name="amount_mode" value="fixed" {{ old('amount_mode', 'fixed') === 'fixed' ? 'checked' : '' }} onchange="document.getElementById('fixed-amount').classList.remove('hidden')">
                        <span class="text-sm">Fixed amount</span>
                    </label>
                    <div id="fixed-amount" class="{{ old('amount_mode', 'fixed') === 'open' ? 'hidden' : '' }} pl-8">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500">₦</span>
                            <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" class="w-40 px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                        @error('amount')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer">
                        <input type="radio" name="amount_mode" value="open" {{ old('amount_mode') === 'open' ? 'checked' : '' }} onchange="document.getElementById('fixed-amount').classList.add('hidden')">
                        <span class="text-sm">Open — client types how much to pay (minimum ₦100)</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">How many times can this link be paid?</label>
                <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer mb-2">
                    <input type="radio" name="reuse_mode" value="one_time" {{ old('reuse_mode', 'one_time') === 'one_time' ? 'checked' : '' }}>
                    <span class="text-sm">One-time — closes after the first successful payment</span>
                </label>
                <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer">
                    <input type="radio" name="reuse_mode" value="reusable" {{ old('reuse_mode') === 'reusable' ? 'checked' : '' }}>
                    <span class="text-sm">Reusable — many clients can pay the same link</span>
                </label>
                @error('reuse_mode')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">Create link</button>
        </form>
    </div>
</div>
@endsection

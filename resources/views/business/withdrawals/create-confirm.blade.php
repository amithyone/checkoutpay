@extends('layouts.business')

@section('title', 'Request Withdrawal - Step 2')
@section('page-title', 'Request Withdrawal')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
            <span class="text-gray-400">Account</span>
            <span class="text-gray-300">→</span>
            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary text-white font-medium">2</span>
            <span>Amount & confirm</span>
        </div>
        <div class="mb-4 sm:mb-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Step 2: Confirm & enter amount</h3>
            <p class="text-xs sm:text-sm text-gray-600 mt-1">Available to withdraw: <span class="font-semibold text-primary">₦{{ number_format($business->getAvailableBalance(), 2) }}</span></p>
        </div>

        <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
            <p class="text-xs text-gray-500 mb-1">Receiving account (verified)</p>
            <p class="font-medium text-gray-900">{{ $account['account_name'] }}</p>
            <p class="text-sm text-gray-600">{{ $account['bank_name'] }} – {{ $account['account_number'] }}</p>
        </div>

        <form method="POST" action="{{ route('business.withdrawals.store') }}">
            @csrf

            <div class="space-y-4">
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" id="amount" step="0.01" min="1" max="{{ max(0, $business->getAvailableBalance()) }}" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        placeholder="Enter amount">
                    @error('amount')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Your password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" id="password" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        placeholder="Enter your password to confirm">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                    <textarea name="notes" id="notes" rows="2"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        placeholder="Any additional notes..."></textarea>
                </div>

                @if(empty($account['saved_account_id']))
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="save_account" id="save_account" value="1"
                        class="rounded border-gray-300 text-primary focus:ring-primary">
                    <label for="save_account" class="text-sm text-gray-700">Save this account for future withdrawals</label>
                </div>
                <div id="default_option" class="hidden ml-6">
                    <input type="checkbox" name="is_default" id="is_default" value="1"
                        class="rounded border-gray-300 text-primary focus:ring-primary">
                    <label for="is_default" class="text-sm text-gray-700">Set as default account</label>
                </div>
                @endif
            </div>

            <div class="mt-6 flex flex-col sm:flex-row gap-3">
                <a href="{{ route('business.withdrawals.create') }}" class="w-full sm:w-auto px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-center text-sm">
                    Back
                </a>
                <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm">
                    Submit withdrawal request
                </button>
            </div>
        </form>
    </div>
</div>

@if(empty($account['saved_account_id']))
@push('scripts')
<script>
document.getElementById('save_account').addEventListener('change', function() {
    document.getElementById('default_option').classList.toggle('hidden', !this.checked);
});
</script>
@endpush
@endif
@endsection

@extends('layouts.admin')

@section('title', 'Create Withdrawal')
@section('page-title', 'Create Withdrawal')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900">Create Withdrawal Request</h3>
            <p class="text-sm text-gray-600 mt-1">Create a withdrawal request on behalf of a business</p>
        </div>

        <form method="POST" action="{{ route('admin.withdrawals.store') }}" id="withdrawal-form">
            @csrf

            <div class="space-y-4">
                <div>
                    <label for="business_id" class="block text-sm font-medium text-gray-700 mb-1">Business <span class="text-red-500">*</span></label>
                    <select name="business_id" id="business_id" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <option value="">Select Business</option>
                        @foreach($businesses as $business)
                            <option value="{{ $business->id }}" data-balance="{{ $business->balance }}">
                                {{ $business->name }} (₦{{ number_format($business->balance, 2) }})
                            </option>
                        @endforeach
                    </select>
                    <p id="balance-display" class="mt-1 text-sm text-gray-600 hidden">
                        Available Balance: <span class="font-semibold text-primary" id="balance-amount">₦0.00</span>
                    </p>
                    @error('business_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" id="amount" step="0.01" min="1" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        placeholder="Enter amount">
                    @error('amount')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="bank_name" class="block text-sm font-medium text-gray-700 mb-1">Bank Name <span class="text-red-500">*</span></label>
                    <select name="bank_name" id="bank_name" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <option value="">Select Bank</option>
                        @foreach(config('banks') as $bank)
                            <option value="{{ $bank['bank_name'] }}">
                                {{ $bank['bank_name'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('bank_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="account_number" class="block text-sm font-medium text-gray-700 mb-1">Account Number <span class="text-red-500">*</span></label>
                    <input type="text" name="account_number" id="account_number" required maxlength="20"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        placeholder="Enter account number">
                    @error('account_number')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="account_name" class="block text-sm font-medium text-gray-700 mb-1">Account Name <span class="text-red-500">*</span></label>
                    <input type="text" name="account_name" id="account_name" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        placeholder="Enter account name">
                    @error('account_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                    <textarea name="notes" id="notes" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        placeholder="Any additional notes..."></textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-4">
                <a href="{{ route('admin.withdrawals.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    Create Withdrawal
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    (function() {
        const businessSelect = document.getElementById('business_id');
        const balanceDisplay = document.getElementById('balance-display');
        const balanceAmount = document.getElementById('balance-amount');
        const amountInput = document.getElementById('amount');

        // Show balance when business is selected
        businessSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const balance = parseFloat(selectedOption.getAttribute('data-balance')) || 0;
                balanceAmount.textContent = '₦' + balance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                balanceDisplay.classList.remove('hidden');
                
                // Set max amount to balance
                amountInput.setAttribute('max', balance);
            } else {
                balanceDisplay.classList.add('hidden');
                amountInput.removeAttribute('max');
            }
        });

        // Validate amount doesn't exceed balance
        amountInput.addEventListener('input', function() {
            const selectedOption = businessSelect.options[businessSelect.selectedIndex];
            if (selectedOption.value) {
                const balance = parseFloat(selectedOption.getAttribute('data-balance')) || 0;
                const amount = parseFloat(this.value) || 0;
                
                if (amount > balance) {
                    this.setCustomValidity('Amount cannot exceed available balance');
                } else {
                    this.setCustomValidity('');
                }
            }
        });
    })();
</script>
@endpush
@endsection

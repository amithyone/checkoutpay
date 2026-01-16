@extends('layouts.admin')

@section('title', 'Edit Account Number')
@section('page-title', 'Edit Account Number')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form action="{{ route('admin.account-numbers.update', $accountNumber) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Type</label>
                    <div class="text-sm text-gray-600">
                        {{ $accountNumber->is_pool ? 'Pool Account' : 'Business-Specific' }}
                    </div>
                </div>

                @if(!$accountNumber->is_pool)
                <div>
                    <label for="business_id" class="block text-sm font-medium text-gray-700 mb-1">Business</label>
                    <select name="business_id" id="business_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">Select Business</option>
                        @foreach($businesses as $business)
                            <option value="{{ $business->id }}" {{ $accountNumber->business_id == $business->id ? 'selected' : '' }}>
                                {{ $business->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div>
                    <label for="account_number" class="block text-sm font-medium text-gray-700 mb-1">Account Number *</label>
                    <input type="text" name="account_number" id="account_number" required maxlength="10"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('account_number', $accountNumber->account_number) }}"
                        placeholder="Enter 10-digit account number">
                    <div id="account_number_validation" class="mt-1 text-sm hidden"></div>
                    @error('account_number')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="account_name" class="block text-sm font-medium text-gray-700 mb-1">Account Name *</label>
                    <input type="text" name="account_name" id="account_name" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('account_name', $accountNumber->account_name) }}">
                    @error('account_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="bank_name" class="block text-sm font-medium text-gray-700 mb-1">Bank Name *</label>
                    <input type="text" name="bank_name" id="bank_name" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('bank_name', $accountNumber->bank_name) }}">
                    @error('bank_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $accountNumber->is_active) ? 'checked' : '' }} class="mr-2">
                        <span class="text-sm text-gray-700">Active</span>
                    </label>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                    <a href="{{ route('admin.account-numbers.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                        Update Account Number
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // Account number validation (only if account number changes)
    (function() {
        const accountNumberInput = document.getElementById('account_number');
        const accountNameInput = document.getElementById('account_name');
        const bankNameInput = document.getElementById('bank_name');
        const validationDiv = document.getElementById('account_number_validation');
        const originalAccountNumber = '{{ $accountNumber->account_number }}';
        let validationTimeout = null;

        if (!accountNumberInput) return;

        accountNumberInput.addEventListener('input', function() {
            const accountNumber = this.value.replace(/\D/g, ''); // Remove non-digits
            this.value = accountNumber;

            // Clear previous timeout
            if (validationTimeout) {
                clearTimeout(validationTimeout);
            }

            // Hide validation message
            validationDiv.classList.add('hidden');
            validationDiv.textContent = '';

            // Only validate if account number changed and is exactly 10 digits
            if (accountNumber !== originalAccountNumber && accountNumber.length === 10) {
                validationDiv.classList.remove('hidden');
                validationDiv.textContent = 'Validating account number...';
                validationDiv.className = 'mt-1 text-sm text-blue-600';

                // Debounce validation
                validationTimeout = setTimeout(() => {
                    validateAccountNumber(accountNumber);
                }, 500);
            } else if (accountNumber !== originalAccountNumber && accountNumber.length > 0) {
                validationDiv.classList.remove('hidden');
                validationDiv.textContent = 'Account number must be 10 digits';
                validationDiv.className = 'mt-1 text-sm text-yellow-600';
            }
        });

        function validateAccountNumber(accountNumber) {
            fetch('{{ route("admin.account-numbers.validate-account") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    account_number: accountNumber
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.valid) {
                    validationDiv.textContent = '✓ Account number validated';
                    validationDiv.className = 'mt-1 text-sm text-green-600';
                    
                    // Auto-fill account name if empty or user wants to update
                    if (accountNameInput && data.account_name) {
                        accountNameInput.value = data.account_name;
                    }
                    
                    // Auto-fill bank name if empty or user wants to update
                    if (bankNameInput && data.bank_name) {
                        bankNameInput.value = data.bank_name;
                    }
                } else {
                    validationDiv.textContent = data.message || 'Invalid account number. Please verify and try again.';
                    validationDiv.className = 'mt-1 text-sm text-red-600';
                }
            })
            .catch(error => {
                console.error('Validation error:', error);
                validationDiv.textContent = 'Error validating account number. Please try again.';
                validationDiv.className = 'mt-1 text-sm text-red-600';
            });
        }
    })();
</script>
@endpush
@endsection

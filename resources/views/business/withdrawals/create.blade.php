@extends('layouts.business')

@section('title', 'Request Withdrawal')
@section('page-title', 'Request Withdrawal')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900">Request Withdrawal</h3>
            <p class="text-sm text-gray-600 mt-1">Available Balance: <span class="font-semibold text-primary">₦{{ number_format($business->balance, 2) }}</span></p>
        </div>

        @php
            $hasAccountNumber = $business->hasAccountNumber();
            $accountDetails = $hasAccountNumber ? $business->primaryAccountNumber() : null;
        @endphp

        @if($hasAccountNumber && $accountDetails)
            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-800">
                    <strong>Account Details:</strong> {{ $accountDetails->bank_name }} - {{ $accountDetails->account_name }} ({{ $accountDetails->account_number }})
                </p>
            </div>
        @endif

        <form method="POST" action="{{ route('business.withdrawals.store') }}">
            @csrf

            <div class="space-y-4">
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                    <input type="number" name="amount" id="amount" step="0.01" min="1" max="{{ $business->balance }}" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        placeholder="Enter amount">
                    @error('amount')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                @if(!$hasAccountNumber)
                <div>
                        <label for="bank_code" class="block text-sm font-medium text-gray-700 mb-1">Bank</label>
                        <select name="bank_code" id="bank_code" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                            <option value="">Select Bank</option>
                            @foreach(config('banks') as $bank)
                                <option value="{{ $bank['code'] }}" data-bank-name="{{ $bank['bank_name'] }}">
                                    {{ $bank['bank_name'] }}
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="bank_name" id="bank_name">
                    @error('bank_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                        @error('bank_code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                </div>

                <div>
                    <label for="account_number" class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
                        <input type="text" name="account_number" id="account_number" required maxlength="10"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                            placeholder="Enter 10-digit account number">
                        <div id="account_number_validation" class="mt-1 text-sm hidden"></div>
                    @error('account_number')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="account_name" class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
                        <input type="text" name="account_name" id="account_name" required readonly
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-gray-50"
                            placeholder="Account name will be auto-filled after validation">
                        <p id="account_name_hint" class="mt-1 text-sm text-gray-500">Enter and validate account number above to auto-fill account name</p>
                        @error('account_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <input type="hidden" name="bank_name" value="{{ $accountDetails->bank_name }}">
                    <input type="hidden" name="account_number" value="{{ $accountDetails->account_number }}">
                    <input type="hidden" name="account_name" value="{{ $accountDetails->account_name }}">
                @endif

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" id="password" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        placeholder="Enter your password">
                    @error('password')
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
                <a href="{{ route('business.withdrawals.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" id="submit_btn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed">
                    Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

@if(!$hasAccountNumber)
@push('scripts')
<script>
    (function() {
        const accountNumberInput = document.getElementById('account_number');
        const accountNameInput = document.getElementById('account_name');
        const bankCodeSelect = document.getElementById('bank_code');
        const bankNameInput = document.getElementById('bank_name');
        const validationDiv = document.getElementById('account_number_validation');
        const accountNameHint = document.getElementById('account_name_hint');
        const submitBtn = document.getElementById('submit_btn');
        const form = document.querySelector('form');
        let validationTimeout = null;
        let isAccountValidated = false;

        // Update bank name when bank is selected
        if (bankCodeSelect) {
            bankCodeSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    bankNameInput.value = selectedOption.getAttribute('data-bank-name');
                    // Reset validation if bank changes
                    isAccountValidated = false;
                    if (submitBtn) {
                        submitBtn.disabled = true;
                    }
                } else {
                    bankNameInput.value = '';
                }
            });
        }

        if (!accountNumberInput) return;

        // Disable submit button initially
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        accountNumberInput.addEventListener('input', function() {
            const accountNumber = this.value.replace(/\D/g, ''); // Remove non-digits
            this.value = accountNumber;

            // Reset validation state
            isAccountValidated = false;
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            // Clear previous timeout
            if (validationTimeout) {
                clearTimeout(validationTimeout);
            }

            // Hide validation message
            validationDiv.classList.add('hidden');
            validationDiv.textContent = '';

            // Reset account name and bank name
            if (accountNameInput) {
                accountNameInput.value = '';
                accountNameInput.classList.add('bg-gray-50');
                accountNameInput.setAttribute('readonly', 'readonly');
            }
            if (accountNameHint) {
                accountNameHint.textContent = 'Enter and validate account number above to auto-fill account name';
                accountNameHint.className = 'mt-1 text-sm text-gray-500';
            }
            if (bankNameInput) {
                bankNameInput.value = '';
            }

            // Only validate if account number is exactly 10 digits and bank is selected
            if (accountNumber.length === 10) {
                const bankCode = bankCodeSelect ? bankCodeSelect.value : '';
                if (!bankCode) {
                    validationDiv.classList.remove('hidden');
                    validationDiv.textContent = 'Please select a bank first';
                    validationDiv.className = 'mt-1 text-sm text-yellow-600';
                    return;
                }

                validationDiv.classList.remove('hidden');
                validationDiv.textContent = 'Validating account number...';
                validationDiv.className = 'mt-1 text-sm text-blue-600';

                // Debounce validation
                validationTimeout = setTimeout(() => {
                    validateAccountNumber(accountNumber, bankCode);
                }, 500);
            } else if (accountNumber.length > 0) {
                validationDiv.classList.remove('hidden');
                validationDiv.textContent = 'Account number must be 10 digits';
                validationDiv.className = 'mt-1 text-sm text-yellow-600';
            } else {
                validationDiv.classList.remove('hidden');
                validationDiv.textContent = 'Please enter account number to validate';
                validationDiv.className = 'mt-1 text-sm text-gray-600';
            }
        });

        function validateAccountNumber(accountNumber, bankCode) {
            fetch('{{ route("business.withdrawals.validate-account") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    account_number: accountNumber,
                    bank_code: bankCode || null
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.valid) {
                    isAccountValidated = true;
                    validationDiv.textContent = '✓ Account number validated: ' + (data.account_name || 'Account verified');
                    validationDiv.className = 'mt-1 text-sm text-green-600';
                    
                    // Auto-fill account name
                    if (accountNameInput && data.account_name) {
                        accountNameInput.value = data.account_name;
                        accountNameInput.classList.remove('bg-gray-50');
                        accountNameInput.removeAttribute('readonly');
                    }
                    if (accountNameHint && data.account_name) {
                        accountNameHint.textContent = '✓ Account name verified: ' + data.account_name;
                        accountNameHint.className = 'mt-1 text-sm text-green-600';
                    }
                    
                    // Auto-fill bank name
                    if (bankNameInput && data.bank_name) {
                        bankNameInput.value = data.bank_name;
                    }

                    // Enable submit button
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                } else {
                    isAccountValidated = false;
                    validationDiv.textContent = data.message || 'Invalid account number. Please verify and try again.';
                    validationDiv.className = 'mt-1 text-sm text-red-600';
                    
                    // Clear account name and bank name
                    if (accountNameInput) {
                        accountNameInput.value = '';
                        accountNameInput.classList.add('bg-gray-50');
                        accountNameInput.setAttribute('readonly', 'readonly');
                    }
                    if (accountNameHint) {
                        accountNameHint.textContent = 'Invalid account number. Please verify and try again.';
                        accountNameHint.className = 'mt-1 text-sm text-red-600';
                    }
                    if (bankNameInput) {
                        bankNameInput.value = '';
                    }

                    // Keep submit button disabled
                    if (submitBtn) {
                        submitBtn.disabled = true;
                    }
                }
            })
            .catch(error => {
                console.error('Validation error:', error);
                isAccountValidated = false;
                validationDiv.textContent = 'Error validating account number. Please try again.';
                validationDiv.className = 'mt-1 text-sm text-red-600';
                
                // Keep submit button disabled
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
            });
        }

        // Prevent form submission if account number is not validated
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!isAccountValidated && accountNumberInput.value.length === 10) {
                    e.preventDefault();
                    validationDiv.classList.remove('hidden');
                    validationDiv.textContent = 'Please wait for account number validation to complete';
                    validationDiv.className = 'mt-1 text-sm text-red-600';
                    return false;
                }
                
                if (!isAccountValidated) {
                    e.preventDefault();
                    validationDiv.classList.remove('hidden');
                    validationDiv.textContent = 'Please validate your account number before submitting';
                    validationDiv.className = 'mt-1 text-sm text-red-600';
                    return false;
                }
            });
        }
    })();
</script>
@endpush
@endif
@endsection

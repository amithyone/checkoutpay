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
                    <input type="hidden" name="bank_code" id="bank_code" value="{{ old('bank_code') }}">
                    <select name="bank_name" id="bank_name" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <option value="">Select Bank</option>
                        @foreach(collect(config('banks'))->sortBy('bank_name') as $bank)
                            <option value="{{ $bank['bank_name'] }}" data-bank-code="{{ $bank['code'] ?? '' }}"
                                @selected(old('bank_name') === $bank['bank_name'])>
                                {{ $bank['bank_name'] }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Bank code is sent for instant transfer (MavonPay) when configured.</p>
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
                    <input type="text" name="account_name" id="account_name" required readonly
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        placeholder="Enter account name">
                    <p id="account_name_status" class="mt-1 text-sm hidden"></p>
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
        const bankSelect = document.getElementById('bank_name');
        const bankCodeInput = document.getElementById('bank_code');
        const accountNumberInput = document.getElementById('account_number');
        const accountNameInput = document.getElementById('account_name');
        const accountNameStatus = document.getElementById('account_name_status');
        let accountValidationTimeout = null;

        function syncBankCodeFromSelect() {
            if (!bankSelect || !bankCodeInput) return;
            const opt = bankSelect.options[bankSelect.selectedIndex];
            bankCodeInput.value = opt ? (opt.getAttribute('data-bank-code') || '') : '';
        }
        syncBankCodeFromSelect();

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

        function resetAccountNameState() {
            if (!accountNameInput || !accountNameStatus) return;
            accountNameStatus.classList.add('hidden');
            accountNameStatus.textContent = '';
            accountNameStatus.className = 'mt-1 text-sm hidden';
            accountNameInput.value = '';
            accountNameInput.classList.remove('bg-green-50', 'border-green-300');
            accountNameInput.classList.add('bg-gray-50');
            accountNameInput.setAttribute('readonly', 'readonly');
        }

        function validateBankAccount() {
            if (!accountNumberInput || !bankSelect || !accountNameInput || !accountNameStatus) return;

            const accountNumber = (accountNumberInput.value || '').replace(/\D/g, '');
            const selectedOption = bankSelect.options[bankSelect.selectedIndex];
            const bankCode = selectedOption ? (selectedOption.getAttribute('data-bank-code') || '') : '';

            if (accountNumber.length !== 10 || !selectedOption.value) {
                return;
            }

            accountNameStatus.classList.remove('hidden');
            accountNameStatus.textContent = 'Validating account details...';
            accountNameStatus.className = 'mt-1 text-sm text-blue-600';

            fetch('{{ route("admin.account-numbers.validate-account") }}', {
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
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.valid && data.account_name) {
                    accountNameInput.value = data.account_name;
                    accountNameInput.classList.remove('bg-gray-50');
                    accountNameInput.removeAttribute('readonly');
                    accountNameInput.classList.add('bg-green-50', 'border-green-300');

                    accountNameStatus.textContent = '✓ Account name retrieved from bank';
                    accountNameStatus.className = 'mt-1 text-sm text-green-600';

                    setTimeout(() => {
                        accountNameInput.classList.remove('bg-green-50', 'border-green-300');
                    }, 3000);
                } else {
                    accountNameStatus.textContent = data.message || 'Unable to verify account. Please check account number and bank.';
                    accountNameStatus.className = 'mt-1 text-sm text-red-600';
                    resetAccountNameState();
                }
            })
            .catch(() => {
                accountNameStatus.textContent = 'Error validating account. Please try again.';
                accountNameStatus.className = 'mt-1 text-sm text-red-600';
                resetAccountNameState();
            });
        }

        if (accountNumberInput && bankSelect) {
            accountNumberInput.addEventListener('input', function() {
                const accountNumber = this.value.replace(/\D/g, '');
                this.value = accountNumber;

                if (accountValidationTimeout) {
                    clearTimeout(accountValidationTimeout);
                }

                resetAccountNameState();

                if (accountNumber.length === 10 && bankSelect.value) {
                    accountValidationTimeout = setTimeout(validateBankAccount, 500);
                } else if (accountNumber.length > 0 && accountNumber.length < 10) {
                    accountNameStatus.classList.remove('hidden');
                    accountNameStatus.textContent = 'Account number must be 10 digits';
                    accountNameStatus.className = 'mt-1 text-sm text-yellow-600';
                }
            });

            bankSelect.addEventListener('change', function() {
                syncBankCodeFromSelect();
                if (accountValidationTimeout) {
                    clearTimeout(accountValidationTimeout);
                }

                resetAccountNameState();

                const accountNumber = (accountNumberInput.value || '').replace(/\D/g, '');
                if (accountNumber.length === 10 && this.value) {
                    accountValidationTimeout = setTimeout(validateBankAccount, 500);
                }
            });
        }
    })();
</script>
@endpush
@endsection

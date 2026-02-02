@extends('layouts.business')

@section('title', 'Request Withdrawal')
@section('page-title', 'Request Withdrawal')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
        <div class="mb-4 sm:mb-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Request Withdrawal</h3>
            <p class="text-xs sm:text-sm text-gray-600 mt-1">Available Balance: <span class="font-semibold text-primary">₦{{ number_format($business->balance, 2) }}</span></p>
        </div>

        @php
            $hasAccountNumber = $business->hasAccountNumber();
            $accountDetails = $hasAccountNumber ? $business->primaryAccountNumber() : null;
        @endphp

        @if($hasAccountNumber && $accountDetails)
            <div class="mb-4 p-3 sm:p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-xs sm:text-sm text-blue-800 break-words">
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
                <!-- Saved Accounts -->
                @if(isset($savedAccounts) && $savedAccounts->count() > 0)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Use Saved Account</label>
                    <select name="saved_account_id" id="saved_account_id" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        onchange="handleSavedAccountChange(this.value)">
                        <option value="">-- Select saved account or enter new --</option>
                        @foreach($savedAccounts as $saved)
                            <option value="{{ $saved->id }}" 
                                data-account-number="{{ $saved->account_number }}"
                                data-account-name="{{ $saved->account_name }}"
                                data-bank-name="{{ $saved->bank_name }}"
                                data-bank-code="{{ $saved->bank_code }}">
                                {{ $saved->bank_name }} - {{ $saved->account_name }} ({{ $saved->account_number }}){{ $saved->is_default ? ' [Default]' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Select a saved account or fill in new details below</p>
                </div>
                @endif

                <div>
                    <label for="bank_code" class="block text-sm font-medium text-gray-700 mb-1">Bank</label>
                    <div class="relative">
                        <input type="text" id="bank_search" autocomplete="off" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                            placeholder="Search bank...">
                        <input type="hidden" name="bank_code" id="bank_code" required>
                        <input type="hidden" name="bank_name" id="bank_name">
                        <div id="bank_dropdown" class="hidden absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto mt-1"></div>
                    </div>
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

                <!-- Save Account Option -->
                <div class="flex items-center space-x-2">
                    <input type="checkbox" name="save_account" id="save_account" value="1"
                        class="rounded border-gray-300 text-primary focus:ring-primary">
                    <label for="save_account" class="text-sm text-gray-700">Save this account for future withdrawals</label>
                </div>
                <div id="default_account_option" class="hidden ml-6 mt-2">
                    <input type="checkbox" name="is_default" id="is_default" value="1"
                        class="rounded border-gray-300 text-primary focus:ring-primary">
                    <label for="is_default" class="text-sm text-gray-700">Set as default account</label>
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

            <div class="mt-4 sm:mt-6 flex flex-col sm:flex-row items-stretch sm:items-center sm:justify-end gap-3">
                <a href="{{ route('business.withdrawals.index') }}" class="w-full sm:w-auto px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-center text-sm">
                    Cancel
                </a>
                <button type="submit" id="submit_btn" class="w-full sm:w-auto px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed text-sm">
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
        const banks = @json(config('banks', []));
        const accountNumberInput = document.getElementById('account_number');
        const accountNameInput = document.getElementById('account_name');
        const bankCodeInput = document.getElementById('bank_code');
        const bankNameInput = document.getElementById('bank_name');
        const bankSearchInput = document.getElementById('bank_search');
        const bankDropdown = document.getElementById('bank_dropdown');
        const savedAccountSelect = document.getElementById('saved_account_id');
        const validationDiv = document.getElementById('account_number_validation');
        const accountNameHint = document.getElementById('account_name_hint');
        const submitBtn = document.getElementById('submit_btn');
        const form = document.querySelector('form');
        const saveAccountCheckbox = document.getElementById('save_account');
        const defaultAccountOption = document.getElementById('default_account_option');
        let validationTimeout = null;
        let isAccountValidated = false;
        let usingSavedAccount = false;

        // Bank search functionality
        if (bankSearchInput && bankDropdown) {
            bankSearchInput.addEventListener('input', function() {
                const search = this.value.toLowerCase();
                if (search.length < 2) {
                    bankDropdown.classList.add('hidden');
                    return;
                }

                const filtered = banks.filter(bank => 
                    bank.bank_name.toLowerCase().includes(search)
                ).slice(0, 10);

                if (filtered.length > 0) {
                    bankDropdown.innerHTML = filtered.map(bank => {
                        const code = bank.code.replace(/'/g, "\\'");
                        const name = bank.bank_name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        return `<div class="px-4 py-2 hover:bg-gray-100 cursor-pointer" onclick="selectBank('${code}', '${name}')">${bank.bank_name}</div>`;
                    }).join('');
                    bankDropdown.classList.remove('hidden');
                } else {
                    bankDropdown.classList.add('hidden');
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!bankSearchInput.contains(e.target) && !bankDropdown.contains(e.target)) {
                    bankDropdown.classList.add('hidden');
                }
            });
        }

        window.selectBank = function(code, name) {
            bankCodeInput.value = code;
            bankNameInput.value = name;
            bankSearchInput.value = name;
            bankDropdown.classList.add('hidden');
            
            // Reset validation if bank changes
            isAccountValidated = false;
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            
            // Trigger account validation if account number is already entered
            if (accountNumberInput && accountNumberInput.value.length === 10) {
                validateAccountNumber(accountNumberInput.value, code);
            }
        };

        // Handle saved account selection
        function handleSavedAccountChange(value) {
            if (!value) {
                usingSavedAccount = false;
                // Clear fields
                accountNumberInput.value = '';
                accountNameInput.value = '';
                bankCodeInput.value = '';
                bankNameInput.value = '';
                bankSearchInput.value = '';
                isAccountValidated = false;
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                return;
            }

            const option = savedAccountSelect.options[savedAccountSelect.selectedIndex];
            const accountNumber = option.getAttribute('data-account-number');
            const accountName = option.getAttribute('data-account-name');
            const bankName = option.getAttribute('data-bank-name');
            const bankCode = option.getAttribute('data-bank-code');

            // Fill in the form fields
            accountNumberInput.value = accountNumber;
            accountNameInput.value = accountName;
            bankCodeInput.value = bankCode;
            bankNameInput.value = bankName;
            bankSearchInput.value = bankName;

            // Mark as validated since it's a saved account
            usingSavedAccount = true;
            isAccountValidated = true;
            if (submitBtn) {
                submitBtn.disabled = false;
            }

            // Show validation success
            validationDiv.classList.remove('hidden');
            validationDiv.textContent = '✓ Using saved account: ' + accountName;
            validationDiv.className = 'mt-1 text-sm text-green-600';

            if (accountNameHint) {
                accountNameHint.textContent = '✓ Using saved account';
                accountNameHint.className = 'mt-1 text-sm text-green-600';
            }
        }

        window.handleSavedAccountChange = handleSavedAccountChange;

        // Show/hide default account option when save checkbox is checked
        if (saveAccountCheckbox && defaultAccountOption) {
            saveAccountCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    defaultAccountOption.classList.remove('hidden');
                } else {
                    defaultAccountOption.classList.add('hidden');
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
                const bankCode = bankCodeInput ? bankCodeInput.value : '';
                if (!bankCode) {
                    validationDiv.classList.remove('hidden');
                    validationDiv.textContent = 'Please select a bank first';
                    validationDiv.className = 'mt-1 text-sm text-yellow-600';
                    return;
                }

                // Clear saved account selection if manually entering
                if (savedAccountSelect) {
                    savedAccountSelect.value = '';
                    usingSavedAccount = false;
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
                if (data.success && data.valid && data.is_active) {
                    isAccountValidated = true;
                    validationDiv.textContent = '✓ Account number validated and active: ' + (data.account_name || 'Account verified');
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
                    
                    // Auto-fill bank name and code
                    if (bankNameInput && data.bank_name) {
                        bankNameInput.value = data.bank_name;
                    }
                    if (bankCodeInput && data.bank_code) {
                        bankCodeInput.value = data.bank_code;
                    }
                    if (bankSearchInput && data.bank_name) {
                        bankSearchInput.value = data.bank_name;
                    }

                    // Enable submit button
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                } else {
                    isAccountValidated = false;
                    validationDiv.textContent = data.message || 'Invalid or inactive account number. Please verify and try again.';
                    validationDiv.className = 'mt-1 text-sm text-red-600';
                    
                    // Clear account name and bank name
                    if (accountNameInput) {
                        accountNameInput.value = '';
                        accountNameInput.classList.add('bg-gray-50');
                        accountNameInput.setAttribute('readonly', 'readonly');
                    }
                    if (accountNameHint) {
                        accountNameHint.textContent = 'Invalid or inactive account number. Please verify and try again.';
                        accountNameHint.className = 'mt-1 text-sm text-red-600';
                    }
                    if (bankNameInput) {
                        bankNameInput.value = '';
                    }
                    if (bankCodeInput) {
                        bankCodeInput.value = '';
                    }
                    if (bankSearchInput) {
                        bankSearchInput.value = '';
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

        // Prevent form submission if account number is not validated (unless using saved account)
        if (form) {
            form.addEventListener('submit', function(e) {
                // Allow submission if using saved account
                if (usingSavedAccount && savedAccountSelect && savedAccountSelect.value) {
                    return true;
                }

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

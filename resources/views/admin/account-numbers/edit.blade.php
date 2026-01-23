@extends('layouts.admin')

@section('title', 'Edit Account Number')
@section('page-title', 'Edit Account Number')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form action="{{ route('admin.account-numbers.update', $accountNumber) }}" method="POST" id="account-number-form" onsubmit="return validateForm()">
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
                    <label for="bank_code" class="block text-sm font-medium text-gray-700 mb-1">Bank *</label>
                    <div class="relative">
                        @php
                            $currentBankCode = old('bank_code');
                            $currentBankName = old('bank_name', $accountNumber->bank_name);
                            if (!$currentBankCode) {
                                // Find bank code from bank name
                                foreach(config('banks') as $bank) {
                                    if ($bank['bank_name'] === $accountNumber->bank_name) {
                                        $currentBankCode = $bank['code'];
                                        break;
                                    }
                                }
                            }
                        @endphp
                        <input type="text" 
                               id="bank_search" 
                               autocomplete="off"
                               placeholder="Search for a bank..."
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                               value="{{ $currentBankName }}"
                               onkeyup="filterBanks(this.value)"
                               onclick="toggleBankDropdown()">
                        <select name="bank_code" id="bank_code"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary hidden"
                            onchange="updateBankName()">
                            <option value="">Select Bank</option>
                            @foreach(config('banks') as $bank)
                                <option value="{{ $bank['code'] }}" data-bank-name="{{ $bank['bank_name'] }}" {{ $currentBankCode == $bank['code'] ? 'selected' : '' }}>
                                    {{ $bank['bank_name'] }}
                                </option>
                            @endforeach
                        </select>
                        <div id="bank_dropdown" class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto hidden">
                            @foreach(config('banks') as $bank)
                                <div class="bank-option px-3 py-2 hover:bg-gray-100 cursor-pointer" 
                                     data-code="{{ $bank['code'] }}" 
                                     data-name="{{ $bank['bank_name'] }}"
                                     onclick="selectBank('{{ $bank['code'] }}', '{{ $bank['bank_name'] }}')">
                                    {{ $bank['bank_name'] }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <input type="hidden" name="bank_name" id="bank_name" value="{{ $currentBankName }}">
                    @error('bank_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @error('bank_code')
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
    // Bank search functionality (same as create page)
    function filterBanks(searchTerm) {
        const dropdown = document.getElementById('bank_dropdown');
        const options = dropdown.querySelectorAll('.bank-option');
        const searchLower = searchTerm.toLowerCase();
        
        dropdown.classList.remove('hidden');
        
        options.forEach(option => {
            const bankName = option.textContent.toLowerCase();
            if (bankName.includes(searchLower)) {
                option.style.display = 'block';
            } else {
                option.style.display = 'none';
            }
        });
    }

    function toggleBankDropdown() {
        const dropdown = document.getElementById('bank_dropdown');
        const searchInput = document.getElementById('bank_search');
        
        if (dropdown.classList.contains('hidden')) {
            dropdown.classList.remove('hidden');
            filterBanks(searchInput.value);
        }
    }

    function selectBank(code, name) {
        const bankCodeSelect = document.getElementById('bank_code');
        const bankNameInput = document.getElementById('bank_name');
        const bankSearchInput = document.getElementById('bank_search');
        const dropdown = document.getElementById('bank_dropdown');
        
        // Set the select value
        bankCodeSelect.value = code;
        bankNameInput.value = name;
        bankSearchInput.value = name;
        
        // Hide dropdown
        dropdown.classList.add('hidden');
        
        // Trigger change event
        bankCodeSelect.dispatchEvent(new Event('change'));
    }

    function updateBankName() {
        const bankCodeSelect = document.getElementById('bank_code');
        const bankNameInput = document.getElementById('bank_name');
        const bankSearchInput = document.getElementById('bank_search');
        const selectedOption = bankCodeSelect.options[bankCodeSelect.selectedIndex];
        
        if (selectedOption.value) {
            const bankName = selectedOption.getAttribute('data-bank-name') || selectedOption.textContent;
            bankNameInput.value = bankName;
            bankSearchInput.value = bankName;
        } else {
            bankNameInput.value = '';
            bankSearchInput.value = '';
        }
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const bankSearchInput = document.getElementById('bank_search');
        const bankDropdown = document.getElementById('bank_dropdown');
        const bankContainer = bankSearchInput ? bankSearchInput.closest('.relative') : null;
        
        if (bankContainer && !bankContainer.contains(event.target)) {
            bankDropdown.classList.add('hidden');
        }
    });

    // Form validation before submission
    function validateForm() {
        const bankNameInput = document.getElementById('bank_name');
        const bankCodeSelect = document.getElementById('bank_code');
        
        // Ensure bank_name is set
        if (!bankNameInput.value || bankNameInput.value.trim() === '') {
            alert('Please select a bank.');
            const bankSearchInput = document.getElementById('bank_search');
            if (bankSearchInput) {
                bankSearchInput.focus();
                toggleBankDropdown();
            }
            return false;
        }
        
        // Ensure bank_code is set if bank_name is set
        if (!bankCodeSelect.value && bankNameInput.value) {
            // Try to find matching bank code
            const bankName = bankNameInput.value.trim();
            const options = bankCodeSelect.options;
            for (let i = 0; i < options.length; i++) {
                if (options[i].getAttribute('data-bank-name') === bankName || options[i].textContent.trim() === bankName) {
                    bankCodeSelect.value = options[i].value;
                    break;
                }
            }
        }
        
        return true;
    }

    // Account number validation (only if account number changes)
    (function() {
        const accountNumberInput = document.getElementById('account_number');
        const accountNameInput = document.getElementById('account_name');
        const bankCodeSelect = document.getElementById('bank_code');
        const bankNameInput = document.getElementById('bank_name');
        const validationDiv = document.getElementById('account_number_validation');
        const originalAccountNumber = '{{ $accountNumber->account_number }}';
        let validationTimeout = null;

        // Update bank name when bank is selected
        if (bankCodeSelect) {
            bankCodeSelect.addEventListener('change', function() {
                updateBankName();
            });
        }

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
            } else if (accountNumber !== originalAccountNumber && accountNumber.length > 0) {
                validationDiv.classList.remove('hidden');
                validationDiv.textContent = 'Account number must be 10 digits';
                validationDiv.className = 'mt-1 text-sm text-yellow-600';
            }
        });

        function validateAccountNumber(accountNumber, bankCode) {
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

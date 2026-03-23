<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC Verification - Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    @include('partials.nav')

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-2xl font-bold mb-4">KYC Verification</h1>
            <p class="text-gray-600 mb-6">
                Please verify your bank account details. The account name will be used as your verified name for rentals.
            </p>

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded mb-4">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('rentals.kyc') }}" method="POST" enctype="multipart/form-data">
                @csrf
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Bank</label>
                    <div class="relative">
                        <input type="text" 
                               id="bank_search" 
                               autocomplete="off"
                               placeholder="Search for a bank..."
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                               onkeyup="filterBanks(this.value)"
                               onclick="toggleBankDropdown()">
                        <select name="bank_code" id="bank_code"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary hidden"
                            onchange="updateBankName()">
                            <option value="">Select Bank</option>
                            @foreach(config('banks') as $bank)
                                <option value="{{ $bank['code'] }}" data-bank-name="{{ $bank['bank_name'] }}">
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
                    <input type="hidden" name="bank_name" id="bank_name">
                    @error('bank_code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Account Number</label>
                    <div class="flex gap-2">
                        <input type="text" id="account_number" name="account_number" maxlength="10" pattern="[0-9]{10}" required
                               class="w-full border border-gray-300 rounded-md px-3 py-2" placeholder="10-digit account number">
                        <button type="button" id="verify_account_btn"
                                class="whitespace-nowrap bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            Verify Account Number
                        </button>
                    </div>
                    @error('account_number')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div id="account_validation" class="mb-4 hidden">
                    <div class="bg-blue-50 border border-blue-200 rounded p-3">
                        <p class="text-sm"><strong>Account Name:</strong> <span id="account_name_display"></span></p>
                    </div>
                </div>
                <input type="hidden" name="verified_account_name" id="verified_account_name" value="">

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">ID card (photo or scan) *</label>
                    <input type="file" name="id_card" accept=".jpg,.jpeg,.png,.pdf" required
                           class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:cursor-pointer border border-gray-300 rounded-lg">
                    <p class="text-xs text-gray-500 mt-1">JPEG, PNG or PDF, max 5MB</p>
                    @error('id_card')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" id="submit_kyc_btn" class="w-full bg-green-600 text-white py-3 rounded-lg hover:bg-green-700 font-medium">
                    Submit KYC
                </button>
            </form>
        </div>
    </div>

    <script>
        function toggleBankDropdown() {
            const dropdown = document.getElementById('bank_dropdown');
            dropdown.classList.toggle('hidden');
        }

        function filterBanks(search) {
            const options = document.querySelectorAll('.bank-option');
            const searchLower = search.toLowerCase();
            options.forEach(option => {
                const name = option.textContent.toLowerCase();
                option.style.display = name.includes(searchLower) ? 'block' : 'none';
            });
        }

        function selectBank(code, name) {
            document.getElementById('bank_code').value = code;
            document.getElementById('bank_name').value = name;
            document.getElementById('bank_search').value = name;
            document.getElementById('bank_dropdown').classList.add('hidden');
        }

        function updateBankName() {
            const select = document.getElementById('bank_code');
            const selectedOption = select.options[select.selectedIndex];
            if (selectedOption.value) {
                document.getElementById('bank_name').value = selectedOption.dataset.bankName;
                document.getElementById('bank_search').value = selectedOption.dataset.bankName;
            }
        }

        async function verifyAccountNumber() {
            const accountNumberInput = document.getElementById('account_number');
            const bankCode = document.getElementById('bank_code').value;
            const accountNumber = (accountNumberInput.value || '').replace(/\D/g, '');
            const statusBox = document.getElementById('account_validation');
            const accountNameDisplay = document.getElementById('account_name_display');
            const verifiedAccountName = document.getElementById('verified_account_name');
            const verifyBtn = document.getElementById('verify_account_btn');

            if (!bankCode) {
                alert('Please select a bank first.');
                return;
            }
            if (accountNumber.length !== 10) {
                alert('Account number must be 10 digits.');
                return;
            }

            verifyBtn.disabled = true;
            verifyBtn.textContent = 'Verifying...';
            statusBox.classList.remove('hidden');
            accountNameDisplay.textContent = 'Checking account details...';

            try {
                const response = await fetch('{{ route("rentals.kyc.verify") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        account_number: accountNumber,
                        bank_code: bankCode
                    })
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to verify account.');
                }

                accountNameDisplay.textContent = data.account_name || 'Verified';
                verifiedAccountName.value = data.account_name || '';
                statusBox.querySelector('div').className = 'bg-green-50 border border-green-200 rounded p-3';
            } catch (error) {
                accountNameDisplay.textContent = error.message || 'Account verification failed.';
                verifiedAccountName.value = '';
                statusBox.querySelector('div').className = 'bg-red-50 border border-red-200 rounded p-3';
            } finally {
                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Verify Account Number';
            }
        }

        document.getElementById('verify_account_btn').addEventListener('click', verifyAccountNumber);
        document.getElementById('account_number').addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
            document.getElementById('verified_account_name').value = '';
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const bankSearch = document.getElementById('bank_search');
            const dropdown = document.getElementById('bank_dropdown');
            if (!bankSearch.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>

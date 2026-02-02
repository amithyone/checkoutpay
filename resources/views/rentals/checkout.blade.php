<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#3C50E0',
                        },
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    @include('partials.nav')

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-3xl font-bold mb-6">Checkout</h1>

        <!-- Cart Items -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Your Rental Items</h2>
            @foreach($items as $item)
                <div class="border-b pb-4 mb-4 last:border-0" data-cart-item>
                    <div class="flex justify-between">
                        <div class="flex-1">
                            <h3 class="font-semibold">{{ $item->name }}</h3>
                            <p class="text-sm text-gray-600">{{ $item->category->name }} • {{ $item->city }}</p>
                            <p class="text-sm text-gray-600">
                                {{ \Carbon\Carbon::parse($item->cart_start_date)->format('M d') }} - 
                                {{ \Carbon\Carbon::parse($item->cart_end_date)->format('M d, Y') }}
                                ({{ \Carbon\Carbon::parse($item->cart_start_date)->diffInDays(\Carbon\Carbon::parse($item->cart_end_date)) + 1 }} days)
                            </p>
                            <p class="text-sm text-gray-600">Quantity: {{ $item->cart_quantity }}</p>
                        </div>
                        <div class="text-right ml-4">
                            <p class="font-semibold">₦{{ number_format($item->getRateForPeriod(\Carbon\Carbon::parse($item->cart_start_date)->diffInDays(\Carbon\Carbon::parse($item->cart_end_date)) + 1) * $item->cart_quantity, 2) }}</p>
                            <form action="{{ route('rentals.cart.remove', $item->id) }}" method="POST" class="mt-2" onsubmit="return confirm('Remove this item from cart?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Account Creation Form -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Create Account</h2>
            <p class="text-gray-600 mb-4">Create an account to proceed with your rental request. We'll verify your account details to get your full name.</p>

            <form action="{{ route('rentals.account.create') }}" method="POST" id="accountForm">
                @csrf
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Email *</label>
                    <input type="email" name="email" id="email" required class="w-full border-gray-300 rounded-md" value="{{ old('email') }}">
                    <p class="text-xs text-gray-500 mt-1">We'll use this to verify your account</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Password *</label>
                        <input type="password" name="password" required minlength="8" class="w-full border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Confirm Password *</label>
                        <input type="password" name="password_confirmation" required class="w-full border-gray-300 rounded-md">
                    </div>
                </div>

                <div class="border-t pt-4 mb-4">
                    <h3 class="font-semibold mb-3">Account Verification (KYC)</h3>
                    <p class="text-sm text-gray-600 mb-4">Enter your bank account details to verify your identity. Your account name will be used as your full name.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Account Number *</label>
                            <input type="text" name="account_number" id="account_number" required maxlength="10" pattern="[0-9]{10}" class="w-full border-gray-300 rounded-md" placeholder="10-digit account number">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Bank *</label>
                            <div class="relative">
                                <input type="text" id="bank_search" autocomplete="off" class="w-full border-gray-300 rounded-md" placeholder="Search bank...">
                                <input type="hidden" name="bank_code" id="bank_code" required>
                                <div id="bank_dropdown" class="hidden absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto mt-1"></div>
                            </div>
                            <p id="verified_account_name" class="text-sm text-green-600 mt-2 hidden"></p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Phone</label>
                        <input type="tel" name="phone" class="w-full border-gray-300 rounded-md" value="{{ old('phone') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Address</label>
                        <input type="text" name="address" class="w-full border-gray-300 rounded-md" value="{{ old('address') }}">
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="w-full bg-primary text-white py-3 rounded-lg hover:bg-primary/90 font-medium">
                    <i class="fas fa-user-check mr-2"></i> Verify & Create Account
                </button>
            </form>
        </div>

        <script>
            const banks = @json(config('banks', []));
            let selectedBank = null;

            // Bank search functionality
            const bankSearch = document.getElementById('bank_search');
            const bankDropdown = document.getElementById('bank_dropdown');
            const bankCodeInput = document.getElementById('bank_code');
            const accountNumberInput = document.getElementById('account_number');
            const verifiedAccountName = document.getElementById('verified_account_name');
            const submitBtn = document.getElementById('submitBtn');

            bankSearch.addEventListener('input', function() {
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

            function selectBank(code, name) {
                selectedBank = { code, name };
                bankSearch.value = name;
                bankCodeInput.value = code;
                bankDropdown.classList.add('hidden');
                verifyAccount();
            }

            // Verify account when both account number and bank are provided
            function verifyAccount() {
                const accountNumber = accountNumberInput.value.replace(/\D/g, '');
                const bankCode = bankCodeInput.value;

                if (accountNumber.length === 10 && bankCode) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Verifying...';

                    fetch('{{ route("rentals.kyc.verify") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            account_number: accountNumber,
                            bank_code: bankCode
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.account_name) {
                            verifiedAccountName.textContent = `Verified: ${data.account_name}`;
                            verifiedAccountName.classList.remove('hidden');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-user-check mr-2"></i> Verify & Create Account';
                        } else {
                            verifiedAccountName.textContent = 'Verification failed. Please check your details.';
                            verifiedAccountName.classList.remove('hidden');
                            verifiedAccountName.classList.remove('text-green-600');
                            verifiedAccountName.classList.add('text-red-600');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-user-check mr-2"></i> Verify & Create Account';
                        }
                    })
                    .catch(error => {
                        console.error('Verification error:', error);
                        verifiedAccountName.textContent = 'Verification error. You can still proceed.';
                        verifiedAccountName.classList.remove('hidden');
                        verifiedAccountName.classList.remove('text-green-600');
                        verifiedAccountName.classList.add('text-yellow-600');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-user-check mr-2"></i> Verify & Create Account';
                    });
                } else {
                    verifiedAccountName.classList.add('hidden');
                }
            }

            accountNumberInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 10);
                if (this.value.length === 10 && bankCodeInput.value) {
                    verifyAccount();
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!bankSearch.contains(e.target) && !bankDropdown.contains(e.target)) {
                    bankDropdown.classList.add('hidden');
                }
            });
        </script>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-20 right-4 z-50 space-y-2"></div>

    <!-- Floating Cart Icon -->
    @php
        $cartCount = count(session('rental_cart', []));
    @endphp
    @if($cartCount > 0)
        <a href="{{ route('rentals.checkout') }}" class="fixed bottom-6 right-6 bg-primary text-white rounded-full p-4 shadow-lg hover:bg-primary/90 transition-all z-50 group">
            <i class="fas fa-shopping-cart text-xl"></i>
            <span id="cart-count" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center">
                {{ $cartCount }}
            </span>
            <span class="absolute right-full mr-3 top-1/2 -translate-y-1/2 bg-gray-900 text-white text-sm px-3 py-2 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                View Cart
            </span>
        </a>
    @endif

    <script>
        // Show toast notification
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            toast.className = `${bgColor} text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-3 min-w-[300px] animate-slide-in`;
            toast.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" class="ml-auto text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Show session messages as toasts
        @if(session('success'))
            showToast('{{ session('success') }}', 'success');
        @endif
        @if(session('error'))
            showToast('{{ session('error') }}', 'error');
        @endif

        // Add CSS animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slide-in {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            .animate-slide-in {
                animation: slide-in 0.3s ease-out;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>

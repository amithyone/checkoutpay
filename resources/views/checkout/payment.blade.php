<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <!-- Header -->
        <div class="text-center mb-8">
            @php
                $logo = \App\Models\Setting::get('site_logo');
                $siteName = \App\Models\Setting::get('site_name', 'CheckoutPay');
            @endphp
            @if($logo && file_exists(storage_path('app/public/' . $logo)))
                <div class="flex justify-center mb-4">
                    <img 
                        src="{{ asset('storage/' . $logo) }}" 
                        alt="{{ $siteName }}" 
                        class="h-16 sm:h-20 object-contain max-w-full"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                    >
                    <div class="hidden items-center justify-center w-12 h-12 bg-green-100 rounded-full">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
            @else
                <div class="inline-flex items-center justify-center w-12 h-12 bg-green-100 rounded-full mb-3">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            @endif
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Payment Details</h1>
            <p class="text-gray-600">Transfer the amount to the account below</p>
        </div>

        <!-- Account Details Card -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <!-- Account Details -->
            <div class="bg-gray-50 rounded-lg p-6 mb-6 space-y-4">
                <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                    <div class="flex items-center">
                        <i class="fas fa-university text-gray-400 mr-3"></i>
                        <span class="text-sm font-medium text-gray-600">Bank Name</span>
                    </div>
                    <span class="text-sm font-semibold text-gray-900">{{ $payment->accountNumberDetails->bank_name ?? 'N/A' }}</span>
                </div>

                <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                    <div class="flex items-center">
                        <i class="fas fa-user text-gray-400 mr-3"></i>
                        <span class="text-sm font-medium text-gray-600">Account Name</span>
                    </div>
                    <span class="text-sm font-semibold text-gray-900">{{ $payment->accountNumberDetails->account_name ?? 'N/A' }}</span>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-hashtag text-gray-400 mr-3"></i>
                        <span class="text-sm font-medium text-gray-600">Account Number</span>
                    </div>
                    <div class="flex items-center">
                        <span id="accountNumber" class="text-sm font-semibold text-gray-900 font-mono mr-2">{{ $payment->account_number }}</span>
                        <button 
                            id="copyBtn" 
                            onclick="copyAccountNumber()"
                            class="text-primary hover:text-primary/80 focus:outline-none"
                            title="Copy account number"
                        >
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Transaction Info -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-600 mt-0.5 mr-3"></i>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-blue-900 mb-1">Transaction ID</p>
                        <p class="text-xs font-mono text-blue-700 break-all">{{ $payment->transaction_id }}</p>
                        <p class="text-xs text-blue-600 mt-2">Keep this ID for reference. You'll be redirected automatically once payment is confirmed.</p>
                    </div>
                </div>
            </div>

            <!-- Amount to Pay -->
            <div class="bg-gradient-to-r from-primary/10 to-primary/5 rounded-lg p-4 mb-6">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Amount to Pay</span>
                    <span class="text-2xl font-bold text-primary">₦{{ number_format($payment->amount, 2) }}</span>
                </div>
            </div>

            <!-- Status Indicator -->
            <div id="statusIndicator" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <i class="fas fa-clock text-yellow-600 mr-3"></i>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-yellow-900">Payment Pending</p>
                        <p class="text-xs text-yellow-700 mt-1">Waiting for payment confirmation...</p>
                    </div>
                </div>
            </div>

            <!-- Instructions -->
            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Payment Instructions</h3>
                <ol class="space-y-2 text-sm text-gray-600">
                    <li class="flex items-start">
                        <span class="flex-shrink-0 w-6 h-6 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-semibold mr-3 mt-0.5">1</span>
                        <span>Transfer the exact amount (<strong>₦{{ number_format($payment->amount, 2) }}</strong>) to the account number above</span>
                    </li>
                    <li class="flex items-start">
                        <span class="flex-shrink-0 w-6 h-6 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-semibold mr-3 mt-0.5">2</span>
                        <span>Use the account name provided above when making the transfer</span>
                    </li>
                    <li class="flex items-start">
                        <span class="flex-shrink-0 w-6 h-6 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-semibold mr-3 mt-0.5">3</span>
                        <span>Payment will be automatically verified within a few minutes</span>
                    </li>
                    <li class="flex items-start">
                        <span class="flex-shrink-0 w-6 h-6 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-semibold mr-3 mt-0.5">4</span>
                        <span>You'll be redirected automatically once payment is verified</span>
                    </li>
                </ol>
            </div>
        </div>
    </div>

    <script>
        const transactionId = '{{ $payment->transaction_id }}';
        const statusCheckUrl = '{{ route("checkout.status", $payment->transaction_id) }}';
        let pollCount = 0;
        const maxPolls = 120; // Poll for 10 minutes (5 seconds * 120)
        const pollInterval = 5000; // 5 seconds

        // Copy account number to clipboard
        function copyAccountNumber() {
            const accountNumber = document.getElementById('accountNumber').textContent;
            navigator.clipboard.writeText(accountNumber).then(() => {
                const copyBtn = document.getElementById('copyBtn');
                const icon = copyBtn.querySelector('i');
                icon.className = 'fas fa-check';
                setTimeout(() => {
                    icon.className = 'fas fa-copy';
                }, 2000);
            });
        }

        // Poll for payment status
        function checkPaymentStatus() {
            if (pollCount >= maxPolls) {
                return; // Stop polling after max attempts
            }

            pollCount++;

            fetch(statusCheckUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.should_redirect) {
                        // Payment approved or rejected - redirect
                        window.location.href = data.redirect_url;
                    }
                })
                .catch(error => {
                    console.error('Error checking status:', error);
                });
        }

        // Start polling
        const pollIntervalId = setInterval(checkPaymentStatus, pollInterval);

        // Also stop polling if user navigates away
        window.addEventListener('beforeunload', () => {
            clearInterval(pollIntervalId);
        });
    </script>
</body>
</html>

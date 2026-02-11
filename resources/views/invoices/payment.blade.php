<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Invoice {{ $invoice->invoice_number }} - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
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
                        primary: { DEFAULT: '#3C50E0' },
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Header -->
        <div class="text-center mb-8">
            @php
                $logo = \App\Models\Setting::get('site_logo');
                $siteName = \App\Models\Setting::get('site_name', 'CheckoutPay');
            @endphp
            @if($logo && file_exists(storage_path('app/public/' . $logo)))
                <div class="flex justify-center mb-4">
                    <img src="{{ asset('storage/' . $logo) }}" alt="{{ $siteName }}" 
                        class="h-16 sm:h-20 object-contain max-w-full"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="hidden items-center justify-center w-12 h-12 bg-primary/10 rounded-full">
                        <i class="fas fa-file-invoice text-primary text-xl"></i>
                    </div>
                </div>
            @else
                <div class="inline-flex items-center justify-center w-12 h-12 bg-primary/10 rounded-full mb-3">
                    <i class="fas fa-file-invoice text-primary text-xl"></i>
                </div>
            @endif
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Invoice Payment</h1>
            <p class="text-gray-600">Invoice #{{ $invoice->invoice_number }}</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Invoice Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Invoice Summary</h2>
                    <div class="space-y-3 text-sm">
                        <div>
                            <p class="text-gray-600">From</p>
                            <p class="font-medium text-gray-900">{{ $invoice->business->name }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600">To</p>
                            <p class="font-medium text-gray-900">{{ $invoice->client_name }}</p>
                            @if($invoice->client_company)
                                <p class="text-gray-500">{{ $invoice->client_company }}</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-gray-600">Invoice Date</p>
                            <p class="font-medium text-gray-900">{{ $invoice->invoice_date->format('M d, Y') }}</p>
                        </div>
                        @if($invoice->due_date)
                        <div>
                            <p class="text-gray-600">Due Date</p>
                            <p class="font-medium text-gray-900">{{ $invoice->due_date->format('M d, Y') }}</p>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Items Summary -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Items</h3>
                    <div class="space-y-2 text-sm">
                        @foreach($invoice->items as $item)
                        <div class="flex justify-between">
                            <span class="text-gray-600">{{ $item->description }}</span>
                            <span class="font-medium">{{ $invoice->currency }} {{ number_format($item->total, 2) }}</span>
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-200 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-medium">{{ $invoice->currency }} {{ number_format($invoice->subtotal, 2) }}</span>
                        </div>
                        @if($invoice->tax_rate > 0)
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax ({{ number_format($invoice->tax_rate, 2) }}%)</span>
                            <span class="font-medium">{{ $invoice->currency }} {{ number_format($invoice->tax_amount, 2) }}</span>
                        </div>
                        @endif
                        @if($invoice->discount_amount > 0)
                        <div class="flex justify-between">
                            <span class="text-gray-600">Discount</span>
                            <span class="font-medium">- {{ $invoice->currency }} {{ number_format($invoice->discount_amount, 2) }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between text-lg font-bold pt-2 border-t border-gray-200">
                            <span>Total</span>
                            <span>{{ $invoice->currency }} {{ number_format($invoice->total_amount, 2) }}</span>
                        </div>
                        @if($allowSplit && $paidSoFar > 0)
                        <div class="flex justify-between text-sm pt-2">
                            <span class="text-gray-600">Paid so far</span>
                            <span class="font-medium text-green-700">{{ $invoice->currency }} {{ number_format($paidSoFar, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm font-semibold">
                            <span class="text-gray-700">Remaining</span>
                            <span>{{ $invoice->currency }} {{ number_format($remaining, 2) }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Payment Details -->
            <div class="lg:col-span-2">
                @if($allowSplit)
                <!-- Split payment: form to create a payment slice -->
                @php $suggestedAmounts = $invoice->getSuggestedSplitAmounts(); @endphp
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Pay in parts</h2>
                    <p class="text-gray-600 mb-4">You can pay the invoice in multiple transfers. Enter the amount you want to pay now and get payment details.</p>
                    @if(count($suggestedAmounts) > 0)
                    <div class="mb-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">Suggested installments (by percentage):</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($suggestedAmounts as $idx => $sug)
                                @if($sug['amount'] <= $remaining && $sug['amount'] >= 0.01)
                                <button type="button" onclick="document.querySelector('input[name=amount]').value={{ number_format($sug['amount'], 2, '.', '') }}"
                                    class="px-3 py-1.5 text-sm border border-primary text-primary rounded-lg hover:bg-primary/10">
                                    {{ number_format($sug['percent'], 1) }}% — {{ $invoice->currency }} {{ number_format($sug['amount'], 2) }}
                                </button>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    @endif
                    @if(session('error'))
                    <p class="text-red-600 text-sm mb-4">{{ session('error') }}</p>
                    @endif
                    @if(session('success'))
                    <p class="text-green-600 text-sm mb-4">{{ session('success') }}</p>
                    @endif
                    <form action="{{ route('invoices.pay.create-payment', $invoice->payment_link_code) }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount to pay ({{ $invoice->currency }})</label>
                            <input type="number" name="amount" step="0.01" min="0.01" max="{{ $remaining }}" value="{{ $remaining > 0 ? number_format($remaining, 2, '.', '') : '' }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary" required>
                            <p class="text-xs text-gray-500 mt-1">Remaining balance: {{ $invoice->currency }} {{ number_format($remaining, 2) }}</p>
                        </div>
                        <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium">
                            <i class="fas fa-plus-circle mr-2"></i>Get payment details
                        </button>
                    </form>
                </div>
                @if($invoice->invoicePayments->isNotEmpty())
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Your payments</h3>
                    <ul class="space-y-2 text-sm">
                        @foreach($invoice->invoicePayments as $ip)
                        <li class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                            <span>{{ $invoice->currency }} {{ number_format($ip->amount, 2) }} — {{ $ip->payment->transaction_id ?? 'N/A' }}</span>
                            @if($ip->payment)
                            <span class="px-2 py-0.5 rounded text-xs font-medium
                                @if($ip->payment->status === 'approved') bg-green-100 text-green-800
                                @elseif($ip->payment->status === 'pending') bg-yellow-100 text-yellow-800
                                @else bg-gray-100 text-gray-800 @endif">
                                {{ $ip->payment->status }}
                            </span>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
                @endif

                @if($selectedPayment && $selectedPayment->account_number)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Instructions</h2>
                    <p class="text-gray-600 mb-6">Transfer the exact amount to the account below:</p>

                    <!-- Account Details -->
                    <div class="bg-gray-50 rounded-lg p-6 mb-6 space-y-4">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                            <div class="flex items-center">
                                <i class="fas fa-university text-gray-400 mr-3"></i>
                                <span class="text-sm font-medium text-gray-600">Bank Name</span>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">{{ $selectedPayment->accountNumberDetails->bank_name ?? 'N/A' }}</span>
                        </div>

                        <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                            <div class="flex items-center">
                                <i class="fas fa-user text-gray-400 mr-3"></i>
                                <span class="text-sm font-medium text-gray-600">Account Name</span>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">{{ $selectedPayment->accountNumberDetails->account_name ?? 'N/A' }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="fas fa-hashtag text-gray-400 mr-3"></i>
                                <span class="text-sm font-medium text-gray-600">Account Number</span>
                            </div>
                            <div class="flex items-center">
                                <span id="accountNumber" class="text-lg font-mono font-semibold text-gray-900 mr-3">{{ $selectedPayment->account_number }}</span>
                                <button onclick="copyAccountNumber()" class="text-primary hover:text-primary/80 focus:outline-none" title="Copy account number">
                                    <i class="fas fa-copy text-xl"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Amount -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-blue-900">Amount to Pay</span>
                            <span class="text-2xl font-bold text-blue-900">{{ $invoice->currency }} {{ number_format($selectedPayment->amount, 2) }}</span>
                        </div>
                    </div>

                    <!-- Transaction ID -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <p class="text-xs text-gray-600 mb-1">Transaction ID</p>
                        <p class="text-sm font-mono text-gray-900">{{ $selectedPayment->transaction_id }}</p>
                    </div>

                    <!-- Info -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-yellow-600 mt-0.5 mr-3"></i>
                            <div class="text-sm text-yellow-800">
                                <p class="font-medium mb-1">Important:</p>
                                <ul class="list-disc list-inside space-y-1">
                                    <li>Transfer the exact amount shown above</li>
                                    <li>Payment will be automatically verified</li>
                                    <li>You will receive a confirmation email once payment is confirmed</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                @elseif($allowSplit && !$selectedPayment)
                <p class="text-gray-600">Enter an amount above and click &quot;Get payment details&quot; to see where to pay.</p>
                @elseif(!empty($paymentSetupError))
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-amber-500 text-4xl mb-4"></i>
                        <p class="text-gray-800 font-medium mb-2">Payment setup issue</p>
                        <p class="text-gray-600 mb-6">{{ $paymentSetupError }}</p>
                        <a href="{{ route('invoices.pay', $invoice->payment_link_code) }}" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                            <i class="fas fa-redo mr-2"></i> Try again
                        </a>
                    </div>
                </div>
                @else
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-primary text-4xl mb-4"></i>
                        <p class="text-gray-600">Setting up payment...</p>
                        <p class="text-sm text-gray-500 mt-2">This usually takes a few seconds. <a href="{{ route('invoices.pay', $invoice->payment_link_code) }}" class="text-primary hover:underline">Refresh page</a> if it takes too long.</p>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        function copyAccountNumber() {
            const el = document.getElementById('accountNumber');
            if (!el) return;
            const accountNumber = el.textContent;
            navigator.clipboard.writeText(accountNumber).then(() => {
                const btn = event.target.closest('button');
                if (!btn) return;
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-copy');
                    icon.classList.add('fa-check');
                    setTimeout(() => {
                        icon.classList.remove('fa-check');
                        icon.classList.add('fa-copy');
                    }, 2000);
                }
            });
        }

        // Auto-refresh payment status every 10 seconds when we have a pending payment
        @if($selectedPayment && $selectedPayment->status === 'pending')
        setInterval(function() {
            fetch('{{ route("checkout.status", $selectedPayment->transaction_id) }}')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'approved') {
                        window.location.href = '{{ route("invoices.pay", $invoice->payment_link_code) }}';
                    }
                });
        }, 10000);
        @endif
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay {{ $link->title }} - {{ $link->business->name }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    @include('partials.tailwind-assets')
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-3xl">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Pay {{ $link->business->name }}</h1>
            <p class="text-gray-600">{{ $link->title }}</p>
        </div>

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm mb-4">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Details</h2>
                    <div class="space-y-3 text-sm">
                        <div>
                            <p class="text-gray-600">Business</p>
                            <p class="font-medium text-gray-900">{{ $link->business->name }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600">Amount</p>
                            <p class="font-medium text-gray-900">{{ $link->formattedAmount() }}</p>
                        </div>
                        @if($link->description)
                            <div>
                                <p class="text-gray-600">Note</p>
                                <p class="text-gray-800">{{ $link->description }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2">
                @if(!empty($paymentSetupError))
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 text-center">
                        <p class="text-gray-800 font-medium mb-2">Payment setup issue</p>
                        <p class="text-gray-600 mb-4">{{ $paymentSetupError }}</p>
                        <a href="{{ route('payment-links.pay', $link->code) }}" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg">Try again</a>
                    </div>
                @elseif($selectedPayment && $selectedPayment->account_number)
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment instructions</h2>
                        <p class="text-gray-600 mb-6">Transfer the exact amount to the account below.</p>
                        <div class="bg-gray-50 rounded-lg p-6 mb-6 space-y-4">
                            <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                                <span class="text-sm font-medium text-gray-600">Bank</span>
                                <span class="text-sm font-semibold">{{ $selectedPayment->accountNumberDetails->bank_name ?? 'N/A' }}</span>
                            </div>
                            <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                                <span class="text-sm font-medium text-gray-600">Account name</span>
                                <span class="text-sm font-semibold">{{ $selectedPayment->accountNumberDetails->account_name ?? 'N/A' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-600">Account number</span>
                                <span class="text-lg font-mono font-semibold">{{ $selectedPayment->account_number }}</span>
                            </div>
                        </div>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4 flex justify-between">
                            <span class="text-sm font-medium text-blue-900">Amount to pay</span>
                            <span class="text-2xl font-bold text-blue-900">{{ $link->currency }} {{ number_format($selectedPayment->amount, 2) }}</span>
                        </div>
                        <p class="text-xs text-gray-500 font-mono mb-4">{{ $selectedPayment->transaction_id }}</p>

                        @if(!empty($cardPaymentsEnabled))
                            <div class="pt-4 border-t border-gray-200">
                                <p class="text-sm font-medium text-gray-900 mb-2">Or pay by card</p>
                                <form method="POST" action="{{ route('payment-links.start', $link->code) }}" class="space-y-3">
                                    @csrf
                                    <input type="hidden" name="payment_method" value="card">
                                    <input type="hidden" name="payer_name" value="{{ $selectedPayment->payer_name }}">
                                    @if($link->isOpenAmount())
                                        <input type="hidden" name="amount" value="{{ $selectedPayment->amount }}">
                                    @endif
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email for card receipt</label>
                                        <input type="email" name="email" value="{{ old('email') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                        @error('email')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                    <button type="submit" class="w-full px-4 py-2 border border-gray-300 rounded-lg font-medium text-gray-800 hover:bg-gray-50">Pay with card instead</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Continue to pay</h2>
                        <form method="POST" action="{{ route('payment-links.start', $link->code) }}" class="space-y-4" id="pay-start-form">
                            @csrf
                            @if(!empty($cardPaymentsEnabled))
                                <div>
                                    <p class="block text-sm font-medium text-gray-700 mb-2">How do you want to pay?</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <label class="flex items-start gap-3 p-4 border rounded-lg cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-blue-50">
                                            <input type="radio" name="payment_method" value="bank_transfer" class="mt-1" {{ old('payment_method', 'bank_transfer') === 'bank_transfer' ? 'checked' : '' }}>
                                            <span>
                                                <span class="block text-sm font-semibold text-gray-900">Account number</span>
                                                <span class="block text-xs text-gray-500 mt-0.5">Transfer to a temporary bank account</span>
                                            </span>
                                        </label>
                                        <label class="flex items-start gap-3 p-4 border rounded-lg cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-blue-50">
                                            <input type="radio" name="payment_method" value="card" class="mt-1" {{ old('payment_method') === 'card' ? 'checked' : '' }}>
                                            <span>
                                                <span class="block text-sm font-semibold text-gray-900">Card payment</span>
                                                <span class="block text-xs text-gray-500 mt-0.5">Pay with a debit or credit card</span>
                                            </span>
                                        </label>
                                    </div>
                                    @error('payment_method')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                            @endif
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Your name</label>
                                <input type="text" name="payer_name" value="{{ old('payer_name') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                @error('payer_name')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div id="card-email-field" class="{{ old('payment_method') === 'card' ? '' : 'hidden' }}">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email" id="card-email-input" value="{{ old('email') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <p class="text-xs text-gray-500 mt-1">Required for card checkout</p>
                                @error('email')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            @if($link->isOpenAmount())
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₦, minimum 100)</label>
                                    <input type="number" name="amount" step="0.01" min="100" value="{{ old('amount') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    @error('amount')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                            @endif
                            <button type="submit" id="pay-submit" class="w-full px-4 py-2 bg-primary text-white rounded-lg font-medium">{{ !empty($cardPaymentsEnabled) ? 'Get account number' : 'Get payment details' }}</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @if(!empty($cardPaymentsEnabled) && (empty($selectedPayment) || empty($selectedPayment->account_number)))
    <script>
    (function () {
        const form = document.getElementById('pay-start-form');
        if (!form) return;
        const emailWrap = document.getElementById('card-email-field');
        const emailInput = document.getElementById('card-email-input');
        const submit = document.getElementById('pay-submit');

        function sync() {
            const method = (form.querySelector('input[name="payment_method"]:checked') || {}).value;
            const isCard = method === 'card';
            emailWrap.classList.toggle('hidden', !isCard);
            emailInput.required = isCard;
            submit.textContent = isCard ? 'Continue to card payment' : 'Get account number';
        }

        form.querySelectorAll('input[name="payment_method"]').forEach(function (el) {
            el.addEventListener('change', sync);
        });
        sync();
    })();
    </script>
    @endif
</body>
</html>

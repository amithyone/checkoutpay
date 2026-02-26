<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay for Rental {{ $rental->rental_number }} - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { primary: { DEFAULT: '#3C50E0' } } } } }</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
@include('partials.nav')
<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Pay for Rental</h1>
        <p class="text-gray-600">Rental #{{ $rental->rental_number }} – {{ $rental->business->name }}</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Rental Summary</h2>
        <div class="space-y-2 text-sm mb-4">
            <p><strong>Period:</strong> {{ $rental->start_date->format('M d, Y') }} – {{ $rental->end_date->format('M d, Y') }} ({{ $rental->days }} days)</p>
            <p><strong>Items:</strong> {{ $rental->items->pluck('name')->join(', ') }}</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-4 text-right">
            <p class="text-2xl font-bold text-gray-900">₦{{ number_format($rental->total_amount, 2) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Instructions</h2>
        <p class="text-gray-600 mb-6">Transfer the exact amount below to complete your rental payment:</p>

        <div class="bg-gray-50 rounded-lg p-6 mb-4 space-y-4">
            <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                <span class="text-sm font-medium text-gray-600">Bank</span>
                <span class="font-medium text-gray-900">{{ $payment->accountNumberDetails->bank_name ?? 'N/A' }}</span>
            </div>
            <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                <span class="text-sm font-medium text-gray-600">Account Name</span>
                <span class="font-medium text-gray-900">{{ $payment->accountNumberDetails->account_name ?? 'N/A' }}</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-sm font-medium text-gray-600">Account Number</span>
                <div class="flex items-center gap-2">
                    <span id="accountNumber" class="font-mono font-semibold text-gray-900">{{ $payment->account_number }}</span>
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('accountNumber').textContent); this.querySelector('i').classList.replace('fa-copy','fa-check'); setTimeout(() => this.querySelector('i').classList.replace('fa-check','fa-copy'), 2000)" class="text-primary hover:opacity-80" title="Copy"><i class="fas fa-copy"></i></button>
                </div>
            </div>
        </div>

        <div class="bg-primary/10 border border-primary/20 rounded-lg p-4 mb-4">
            <div class="flex justify-between items-center">
                <span class="font-medium text-gray-700">Amount to pay</span>
                <span class="text-xl font-bold text-gray-900">₦{{ number_format($payment->amount, 2) }}</span>
            </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 mb-4">
            <p class="text-xs text-gray-600 mb-1">Transaction ID (use as reference if required)</p>
            <p class="font-mono text-sm text-gray-900">{{ $payment->transaction_id }}</p>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
            <p class="text-sm text-amber-800"><strong>Important:</strong> Transfer the exact amount. Payment will be verified automatically and your rental will be confirmed once received.</p>
        </div>
    </div>

    @if($payment->expires_at)
    <p class="text-center text-sm text-gray-500">This payment link expires {{ $payment->expires_at->format('M d, Y H:i') }}.</p>
    @endif
</div>
</body>
</html>

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
        <p class="text-gray-600 mb-6">Transfer the exact amount below to complete your rental payment.</p>

        @php
            $primaryExpired = $primaryPayment?->isExpired() ?? false;
            $secondaryExpired = $secondaryPayment?->isExpired() ?? false;
        @endphp

        <div class="space-y-4">
            @if($primaryPayment)
                <div class="bg-gray-50 rounded-lg p-6 space-y-4 @if($primaryExpired) opacity-70 @endif">
                    <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                        <span class="text-sm font-medium text-gray-600">
                            {{ $primaryPayment && $primaryPayment->isExternalGatewayPayment() ? 'External (MEVONRUBIES)' : 'Internal account' }}
                        </span>
                        <span class="text-xs {{ $primaryExpired ? 'text-amber-700' : 'text-gray-500' }}">
                            {{ $primaryExpired ? 'Expired' : 'Available' }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                        <span class="text-sm font-medium text-gray-600">Bank</span>
                        <span class="font-medium text-gray-900">{{ $primaryPayment->accountNumberDetails->bank_name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                        <span class="text-sm font-medium text-gray-600">Account Name</span>
                        <span class="font-medium text-gray-900">{{ $primaryPayment->accountNumberDetails->account_name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-600">Account Number</span>
                        <div class="flex items-center gap-2">
                            <span id="primaryAccountNumber" class="font-mono font-semibold text-gray-900">{{ $primaryPayment->account_number }}</span>
                            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('primaryAccountNumber').textContent)" class="text-primary hover:opacity-80" title="Copy"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-600">Transaction ID</span>
                        <span class="font-mono text-sm text-gray-900">{{ $primaryPayment->transaction_id }}</span>
                    </div>
                </div>
            @endif

            @if($secondaryPayment)
                <div class="bg-gray-50 rounded-lg p-6 space-y-4 @if($secondaryExpired) opacity-70 @endif">
                    <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                        <span class="text-sm font-medium text-gray-600">
                            {{ $secondaryPayment && $secondaryPayment->isExternalGatewayPayment() ? 'External (MEVONRUBIES)' : 'Internal account' }}
                        </span>
                        <span class="text-xs {{ $secondaryExpired ? 'text-amber-700' : 'text-gray-500' }}">
                            {{ $secondaryExpired ? 'Expired' : 'Available' }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                        <span class="text-sm font-medium text-gray-600">Bank</span>
                        <span class="font-medium text-gray-900">{{ $secondaryPayment->accountNumberDetails->bank_name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                        <span class="text-sm font-medium text-gray-600">Account Name</span>
                        <span class="font-medium text-gray-900">{{ $secondaryPayment->accountNumberDetails->account_name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-600">Account Number</span>
                        <div class="flex items-center gap-2">
                            <span id="secondaryAccountNumber" class="font-mono font-semibold text-gray-900">{{ $secondaryPayment->account_number }}</span>
                            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('secondaryAccountNumber').textContent)" class="text-primary hover:opacity-80" title="Copy"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-600">Transaction ID</span>
                        <span class="font-mono text-sm text-gray-900">{{ $secondaryPayment->transaction_id }}</span>
                    </div>
                </div>
            @endif

            <div class="bg-primary/10 border border-primary/20 rounded-lg p-4">
                <div class="flex justify-between items-center">
                    <span class="font-medium text-gray-700">Amount to pay</span>
                    <span class="text-xl font-bold text-gray-900">₦{{ number_format($rental->total_amount, 2) }}</span>
                </div>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                <p class="text-sm text-amber-800">
                    <strong>Important:</strong> Transfer the exact amount to one of the account numbers above.
                    Payment will be verified automatically and your rental will be confirmed once received.
                </p>
            </div>
        </div>
    </div>

    @php
        $primaryExpiresAt = $primaryPayment?->expires_at;
        $secondaryExpiresAt = $secondaryPayment?->expires_at;
        $displayExpiry = $primaryExpiresAt ?? $secondaryExpiresAt;
    @endphp
    @if($displayExpiry)
        <p class="text-center text-sm text-gray-500">This payment link expires {{ $displayExpiry->format('M d, Y H:i') }}.</p>
    @endif
</div>
</body>
</html>

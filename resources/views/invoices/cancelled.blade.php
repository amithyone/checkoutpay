<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Cancelled - {{ $invoice->invoice_number }}</title>
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
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-6">
                <i class="fas fa-times-circle text-gray-600 text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Invoice Cancelled</h1>
            <p class="text-gray-600 mb-6">This invoice has been cancelled and is no longer available for payment.</p>

            <div class="bg-gray-50 rounded-lg p-6 mb-6 text-left">
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Invoice Number:</span>
                        <span class="font-medium text-gray-900">{{ $invoice->invoice_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Amount:</span>
                        <span class="font-medium text-gray-900">{{ $invoice->currency }} {{ number_format($invoice->total_amount, 2) }}</span>
                    </div>
                </div>
            </div>

            <p class="text-sm text-gray-600">
                If you have any questions, please contact <strong>{{ $invoice->business->name }}</strong>.
            </p>
        </div>
    </div>
</body>
</html>

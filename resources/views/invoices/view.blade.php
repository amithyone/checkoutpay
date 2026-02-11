<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }} - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
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
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <a href="{{ route('invoices.pay', $invoice->payment_link_code) }}" class="text-primary hover:underline text-sm inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to payment
            </a>
            <div class="flex gap-2">
                <a href="{{ route('invoices.view.pdf', $invoice->payment_link_code) }}" target="_blank" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                    <i class="fas fa-file-pdf mr-1"></i> View PDF
                </a>
                @if(!$invoice->isPaid() && $invoice->status !== 'cancelled')
                <a href="{{ route('invoices.pay', $invoice->payment_link_code) }}" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    <i class="fas fa-credit-card mr-1"></i> Pay invoice
                </a>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
            <div class="flex flex-col sm:flex-row sm:justify-between gap-4 mb-6 pb-6 border-b border-gray-200">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $invoice->invoice_number }}</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ $invoice->business->name }}</p>
                </div>
                <div class="flex items-center">
                    <span class="px-3 py-1 text-sm font-medium rounded-full
                        @if($invoice->status === 'paid') bg-green-100 text-green-800
                        @elseif($invoice->status === 'cancelled') bg-gray-100 text-gray-800
                        @elseif($invoice->status === 'overdue') bg-red-100 text-red-800
                        @else bg-blue-100 text-blue-800
                        @endif">
                        {{ ucfirst($invoice->status) }}
                    </span>
                </div>
            </div>

            @if($invoice->isPaid())
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-600 mr-3"></i>
                    <div>
                        <p class="font-medium text-green-900">Invoice paid</p>
                        <p class="text-sm text-green-700">Paid on {{ $invoice->paid_at->format('M d, Y H:i') }}</p>
                    </div>
                </div>
            </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Bill to</h3>
                    <p class="font-semibold text-gray-900">{{ $invoice->client_name }}</p>
                    @if($invoice->client_company)<p class="text-sm text-gray-600">{{ $invoice->client_company }}</p>@endif
                    <p class="text-sm text-gray-600">{{ $invoice->client_email }}</p>
                    @if($invoice->client_phone)<p class="text-sm text-gray-600">{{ $invoice->client_phone }}</p>@endif
                    @if($invoice->client_address)<p class="text-sm text-gray-600 mt-2">{{ $invoice->client_address }}</p>@endif
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Details</h3>
                    <p class="text-sm text-gray-900">Invoice date: {{ $invoice->invoice_date->format('M d, Y') }}</p>
                    @if($invoice->due_date)<p class="text-sm text-gray-900">Due date: {{ $invoice->due_date->format('M d, Y') }}</p>@endif
                    @if($invoice->reference_number)<p class="text-sm text-gray-900">Reference: {{ $invoice->reference_number }}</p>@endif
                </div>
            </div>

            <div class="border-t border-gray-200 pt-6">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unit price</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($invoice->items as $item)
                        <tr>
                            <td class="px-4 py-3 text-gray-900">{{ $item->description }}</td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ number_format($item->quantity, 2) }} {{ $item->unit }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $invoice->currency }} {{ number_format($item->unit_price, 2) }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900">{{ $invoice->currency }} {{ number_format($item->total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 pt-6 mt-6 flex justify-end">
                <div class="w-full sm:w-80 space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-600">Subtotal</span><span>{{ $invoice->currency }} {{ number_format($invoice->subtotal, 2) }}</span></div>
                    @if($invoice->tax_rate > 0)
                    <div class="flex justify-between"><span class="text-gray-600">Tax ({{ number_format($invoice->tax_rate, 2) }}%)</span><span>{{ $invoice->currency }} {{ number_format($invoice->tax_amount, 2) }}</span></div>
                    @endif
                    @if($invoice->discount_amount > 0)
                    <div class="flex justify-between"><span class="text-gray-600">Discount</span><span>- {{ $invoice->currency }} {{ number_format($invoice->discount_amount, 2) }}</span></div>
                    @endif
                    <div class="flex justify-between text-lg font-bold pt-2 border-t border-gray-200">
                        <span>Total</span>
                        <span>{{ $invoice->currency }} {{ number_format($invoice->total_amount, 2) }}</span>
                    </div>
                    @if($invoice->isPaid())
                    <div class="flex justify-between text-green-600 pt-1"><span>Paid</span><span>{{ $invoice->currency }} {{ number_format($invoice->paid_amount ?? $invoice->total_amount, 2) }}</span></div>
                    @endif
                </div>
            </div>

            @if($invoice->notes || $invoice->terms_and_conditions)
            <div class="border-t border-gray-200 pt-6 mt-6 text-sm text-gray-600">
                @if($invoice->notes)<p class="mb-2"><strong>Notes:</strong><br>{{ $invoice->notes }}</p>@endif
                @if($invoice->terms_and_conditions)<p><strong>Terms:</strong><br>{{ $invoice->terms_and_conditions }}</p>@endif
            </div>
            @endif
        </div>
    </div>
</body>
</html>

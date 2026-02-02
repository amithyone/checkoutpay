@extends('layouts.admin')

@section('title', 'Invoice ' . $invoice->invoice_number)
@section('page-title', 'Invoice Details')

@section('content')
<div class="space-y-6">
    <!-- Header Actions -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ $invoice->invoice_number }}</h3>
                <p class="text-sm text-gray-600 mt-1">Created {{ $invoice->created_at->format('M d, Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.invoices.edit', $invoice) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                    <i class="fas fa-edit mr-1"></i> Edit
                </a>
                <form action="{{ route('admin.invoices.send', $invoice) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark text-sm">
                        <i class="fas fa-paper-plane mr-1"></i> Send Email
                    </button>
                </form>
                <a href="{{ route('admin.invoices.view-pdf', $invoice) }}" target="_blank" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                    <i class="fas fa-file-pdf mr-1"></i> View PDF
                </a>
                <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                    <i class="fas fa-download mr-1"></i> Download
                </a>
            </div>
        </div>

        <!-- Status Badge -->
        <div class="mb-4">
            @if($invoice->status === 'paid')
                <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">Paid</span>
            @elseif($invoice->status === 'sent')
                <span class="px-3 py-1 text-sm font-medium bg-blue-100 text-blue-800 rounded-full">Sent</span>
            @elseif($invoice->status === 'viewed')
                <span class="px-3 py-1 text-sm font-medium bg-purple-100 text-purple-800 rounded-full">Viewed</span>
            @elseif($invoice->status === 'overdue')
                <span class="px-3 py-1 text-sm font-medium bg-red-100 text-red-800 rounded-full">Overdue</span>
            @elseif($invoice->status === 'cancelled')
                <span class="px-3 py-1 text-sm font-medium bg-gray-100 text-gray-800 rounded-full">Cancelled</span>
            @else
                <span class="px-3 py-1 text-sm font-medium bg-yellow-100 text-yellow-800 rounded-full">Draft</span>
            @endif
        </div>
    </div>

    <!-- Invoice Details -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <h3 class="text-sm font-medium text-gray-500 mb-2">Bill To</h3>
                <div class="text-sm text-gray-900">
                    <p class="font-semibold">{{ $invoice->client_name }}</p>
                    @if($invoice->client_company)
                        <p>{{ $invoice->client_company }}</p>
                    @endif
                    <p>{{ $invoice->client_email }}</p>
                    @if($invoice->client_phone)
                        <p>{{ $invoice->client_phone }}</p>
                    @endif
                    @if($invoice->client_address)
                        <p class="mt-2">{{ $invoice->client_address }}</p>
                    @endif
                </div>
            </div>
            <div>
                <h3 class="text-sm font-medium text-gray-500 mb-2">Business</h3>
                <div class="text-sm text-gray-900">
                    <p class="font-semibold">{{ $invoice->business->name }}</p>
                    <p>{{ $invoice->business->email }}</p>
                    <p class="text-xs text-gray-500 mt-1">Business ID: {{ $invoice->business->business_id }}</p>
                </div>
                <h3 class="text-sm font-medium text-gray-500 mb-2 mt-4">Invoice Details</h3>
                <div class="text-sm text-gray-900 space-y-1">
                    <p><span class="text-gray-600">Invoice Date:</span> {{ $invoice->invoice_date->format('M d, Y') }}</p>
                    @if($invoice->due_date)
                        <p><span class="text-gray-600">Due Date:</span> {{ $invoice->due_date->format('M d, Y') }}</p>
                    @endif
                    @if($invoice->reference_number)
                        <p><span class="text-gray-600">Reference:</span> {{ $invoice->reference_number }}</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="border-t border-gray-200 pt-6">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Quantity</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($invoice->items as $item)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">{{ $item->description }}</div>
                            @if($item->notes)
                                <div class="text-xs text-gray-500 mt-1">{{ $item->notes }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-gray-600">
                            {{ number_format($item->quantity, 2) }} {{ $item->unit }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-gray-600">
                            {{ $invoice->currency }} {{ number_format($item->unit_price, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-medium text-gray-900">
                            {{ $invoice->currency }} {{ number_format($item->total, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="border-t border-gray-200 pt-6">
            <div class="flex justify-end">
                <div class="w-full md:w-96 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="font-medium">{{ $invoice->currency }} {{ number_format($invoice->subtotal, 2) }}</span>
                    </div>
                    @if($invoice->tax_rate > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Tax ({{ number_format($invoice->tax_rate, 2) }}%):</span>
                        <span class="font-medium">{{ $invoice->currency }} {{ number_format($invoice->tax_amount, 2) }}</span>
                    </div>
                    @endif
                    @if($invoice->discount_amount > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">
                            Discount
                            @if($invoice->discount_type === 'percentage')
                                ({{ number_format($invoice->discount_amount, 2) }}%)
                            @endif
                            :
                        </span>
                        <span class="font-medium">- {{ $invoice->currency }} {{ number_format($invoice->discount_amount, 2) }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-lg font-bold pt-2 border-t border-gray-200">
                        <span>Total:</span>
                        <span>{{ $invoice->currency }} {{ number_format($invoice->total_amount, 2) }}</span>
                    </div>
                    @if($invoice->isPaid())
                    <div class="flex justify-between text-sm text-green-600 pt-2">
                        <span>Paid Amount:</span>
                        <span class="font-medium">{{ $invoice->currency }} {{ number_format($invoice->paid_amount ?? $invoice->total_amount, 2) }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Notes & Terms -->
        @if($invoice->notes || $invoice->terms_and_conditions)
        <div class="border-t border-gray-200 pt-6 mt-6">
            @if($invoice->notes)
            <div class="mb-4">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Notes</h4>
                <p class="text-sm text-gray-600 whitespace-pre-line">{{ $invoice->notes }}</p>
            </div>
            @endif
            @if($invoice->terms_and_conditions)
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-2">Terms & Conditions</h4>
                <p class="text-sm text-gray-600 whitespace-pre-line">{{ $invoice->terms_and_conditions }}</p>
            </div>
            @endif
        </div>
        @endif
    </div>

    <!-- Payment Link -->
    @if(!$invoice->isPaid() && $invoice->status !== 'cancelled')
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Link</h3>
        <div class="flex items-center gap-3">
            <input type="text" id="payment-link" readonly value="{{ route('invoices.pay', $invoice->payment_link_code) }}"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-sm">
            <button onclick="copyPaymentLink()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark text-sm">
                <i class="fas fa-copy mr-1"></i> Copy Link
            </button>
            <a href="{{ route('invoices.pay', $invoice->payment_link_code) }}" target="_blank" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                <i class="fas fa-external-link-alt mr-1"></i> Open
            </a>
        </div>
    </div>
    @endif

    <!-- Payment Information -->
    @if($invoice->payment)
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-600">Transaction ID:</span>
                <span class="font-medium text-gray-900">{{ $invoice->payment->transaction_id }}</span>
            </div>
            <div>
                <span class="text-gray-600">Status:</span>
                <span class="font-medium text-gray-900">{{ ucfirst($invoice->payment->status) }}</span>
            </div>
            <div>
                <span class="text-gray-600">Amount:</span>
                <span class="font-medium text-gray-900">₦{{ number_format($invoice->payment->amount, 2) }}</span>
            </div>
            <div>
                <span class="text-gray-600">Account Number:</span>
                <span class="font-medium text-gray-900 font-mono">{{ $invoice->payment->account_number }}</span>
            </div>
        </div>
        <div class="mt-4">
            <a href="{{ route('admin.payments.show', $invoice->payment) }}" class="text-sm text-primary hover:underline">
                View Payment Details <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
    @endif

    <!-- Activity Log -->
    @if($invoice->viewed_at || $invoice->sent_at || $invoice->paid_at)
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Activity</h3>
        <div class="space-y-3 text-sm">
            @if($invoice->sent_at)
            <div class="flex items-center text-gray-600">
                <i class="fas fa-paper-plane text-blue-500 mr-3"></i>
                <span>Invoice sent via email on {{ $invoice->sent_at->format('M d, Y H:i') }}</span>
            </div>
            @endif
            @if($invoice->viewed_at)
            <div class="flex items-center text-gray-600">
                <i class="fas fa-eye text-purple-500 mr-3"></i>
                <span>Viewed {{ $invoice->view_count }} time(s), last viewed on {{ $invoice->viewed_at->format('M d, Y H:i') }}</span>
            </div>
            @endif
            @if($invoice->paid_at)
            <div class="flex items-center text-green-600">
                <i class="fas fa-check-circle mr-3"></i>
                <span>Paid on {{ $invoice->paid_at->format('M d, Y H:i') }}</span>
            </div>
            @endif
        </div>
    </div>
    @endif
</div>

<script>
function copyPaymentLink() {
    const link = document.getElementById('payment-link');
    link.select();
    document.execCommand('copy');
    
    // Show feedback
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check mr-1"></i> Copied!';
    btn.classList.add('bg-green-600');
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.classList.remove('bg-green-600');
    }, 2000);
}
</script>
@endsection

@extends('layouts.admin')

@section('title', 'Invoices')
@section('page-title', 'Invoice Management')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">All Invoices</h3>
            <p class="text-sm text-gray-600 mt-1">Manage invoices across all businesses</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Invoices -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Invoices</p>
                    <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['total']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-file-invoice text-blue-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center text-sm">
                <span class="text-gray-600">This Month: {{ number_format($stats['this_month_total']) }}</span>
            </div>
        </div>

        <!-- Paid Invoices -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Paid Invoices</p>
                    <h3 class="text-2xl font-bold text-green-600">{{ number_format($stats['paid']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center text-sm">
                <span class="text-gray-600">Paid Amount: ₦{{ number_format($stats['paid_amount'] ?? 0, 2) }}</span>
            </div>
        </div>

        <!-- Pending Invoices -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Pending</p>
                    <h3 class="text-2xl font-bold text-yellow-600">{{ number_format($stats['sent'] + $stats['viewed']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center text-sm">
                <span class="text-gray-600">Pending Amount: ₦{{ number_format($stats['pending_amount'] ?? 0, 2) }}</span>
            </div>
        </div>

        <!-- Total Amount -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Amount</p>
                    <h3 class="text-2xl font-bold text-gray-900">₦{{ number_format($stats['total_amount'] ?? 0, 2) }}</h3>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-purple-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center text-sm">
                <span class="text-gray-600">This Month: ₦{{ number_format($stats['this_month_amount'] ?? 0, 2) }}</span>
            </div>
        </div>
    </div>

    <!-- Status Breakdown -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="text-center">
                <p class="text-xs text-gray-600 mb-1">Draft</p>
                <p class="text-lg font-semibold text-gray-900">{{ number_format($stats['draft']) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="text-center">
                <p class="text-xs text-gray-600 mb-1">Sent</p>
                <p class="text-lg font-semibold text-blue-600">{{ number_format($stats['sent']) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="text-center">
                <p class="text-xs text-gray-600 mb-1">Viewed</p>
                <p class="text-lg font-semibold text-purple-600">{{ number_format($stats['viewed']) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="text-center">
                <p class="text-xs text-gray-600 mb-1">Paid</p>
                <p class="text-lg font-semibold text-green-600">{{ number_format($stats['paid']) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="text-center">
                <p class="text-xs text-gray-600 mb-1">Overdue</p>
                <p class="text-lg font-semibold text-red-600">{{ number_format($stats['overdue']) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="text-center">
                <p class="text-xs text-gray-600 mb-1">Cancelled</p>
                <p class="text-lg font-semibold text-gray-600">{{ number_format($stats['cancelled']) }}</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" 
                    placeholder="Invoice #, Client Name, Email" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Status</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Sent</option>
                    <option value="viewed" {{ request('status') === 'viewed' ? 'selected' : '' }}>Viewed</option>
                    <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>Overdue</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Business</label>
                <select name="business_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Businesses</option>
                    @foreach($businesses as $business)
                        <option value="{{ $business->id }}" {{ request('business_id') == $business->id ? 'selected' : '' }}>
                            {{ $business->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">From Date</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" 
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" 
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark text-sm">
                    <i class="fas fa-search mr-2"></i> Search
                </button>
                <a href="{{ route('admin.invoices.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm">Clear</a>
            </div>
        </form>
    </div>

    <!-- Invoices Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($invoices as $invoice)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $invoice->invoice_number }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900">{{ $invoice->business->name }}</div>
                            <div class="text-xs text-gray-500">{{ $invoice->business->business_id }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $invoice->client_name }}</div>
                            <div class="text-xs text-gray-500">{{ $invoice->client_email }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                            {{ $invoice->currency }} {{ number_format($invoice->total_amount, 2) }}
                        </td>
                        <td class="px-6 py-4">
                            @if($invoice->status === 'paid')
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Paid</span>
                            @elseif($invoice->status === 'sent')
                                <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">Sent</span>
                            @elseif($invoice->status === 'viewed')
                                <span class="px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">Viewed</span>
                            @elseif($invoice->status === 'overdue')
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Overdue</span>
                            @elseif($invoice->status === 'cancelled')
                                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Cancelled</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Draft</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : '-' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $invoice->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2 flex-wrap">
                                <a href="{{ route('admin.invoices.show', $invoice) }}" class="text-sm text-primary hover:underline">
                                    View
                                </a>
                                <a href="{{ route('admin.invoices.edit', $invoice) }}" class="text-sm text-gray-600 hover:text-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @if(!$invoice->isPaid() && in_array($invoice->status, ['sent', 'viewed', 'overdue']))
                                <button type="button" onclick="showMarkPaidModal({{ $invoice->id }}, '{{ addslashes($invoice->invoice_number) }}', {{ $invoice->total_amount }}, '{{ addslashes($invoice->currency) }}')"
                                    class="text-sm text-green-600 hover:text-green-700 font-medium">
                                    <i class="fas fa-check-circle mr-1"></i> Mark as paid
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-file-invoice text-gray-400 text-4xl mb-3"></i>
                                <p class="text-sm text-gray-500">No invoices found</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($invoices->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $invoices->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Mark as Paid Modal (like Manually Approve Transaction) -->
<div id="markPaidModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-3 sm:p-4">
    <div class="bg-white rounded-lg p-4 sm:p-6 max-w-md w-full max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Mark Invoice as Paid</h3>
        <form id="markPaidForm" method="POST">
            @csrf
            <div class="mb-3 sm:mb-4">
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Invoice</label>
                <p class="text-sm font-mono text-gray-900 bg-gray-50 p-2 rounded" id="modal-invoice-number"></p>
            </div>
            <div class="mb-3 sm:mb-4">
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Total Amount</label>
                <p class="text-base sm:text-lg font-bold text-gray-900" id="modal-invoice-amount"></p>
            </div>
            <div class="mb-3 sm:mb-4">
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Link to received email / confirmation note</label>
                <input type="text" name="paid_confirmation_notes" id="modal-paid-confirmation-notes" maxlength="500"
                    class="w-full px-2 sm:px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-xs sm:text-sm"
                    placeholder="e.g. Gmail link or 'Confirmed via email from business on ...'">
                <p class="text-xs text-gray-500 mt-1">Optional. Paste the email link or a short note for audit.</p>
            </div>
            <div class="flex flex-col sm:flex-row justify-end gap-2 sm:gap-3">
                <button type="button" onclick="closeMarkPaidModal()"
                    class="px-3 sm:px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-xs sm:text-sm">
                    Cancel
                </button>
                <button type="submit"
                    class="px-3 sm:px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-xs sm:text-sm">
                    <i class="fas fa-check-circle mr-2"></i> Mark as paid & send receipt
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function showMarkPaidModal(invoiceId, invoiceNumber, totalAmount, currency) {
    const form = document.getElementById('markPaidForm');
    form.action = "{{ route('admin.invoices.mark-paid', ['invoice' => '__ID__']) }}".replace('__ID__', invoiceId);

    document.getElementById('modal-invoice-number').textContent = invoiceNumber;
    const sym = currency === 'NGN' ? '₦' : currency === 'USD' ? '$' : currency === 'GBP' ? '£' : currency === 'EUR' ? '€' : currency + ' ';
    document.getElementById('modal-invoice-amount').textContent = sym + Number(totalAmount).toLocaleString('en-NG', { minimumFractionDigits: 2 });
    document.getElementById('modal-paid-confirmation-notes').value = '';

    document.getElementById('markPaidModal').classList.remove('hidden');
}

function closeMarkPaidModal() {
    document.getElementById('markPaidModal').classList.add('hidden');
}

document.getElementById('markPaidModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeMarkPaidModal();
});
</script>
@endpush
@endsection

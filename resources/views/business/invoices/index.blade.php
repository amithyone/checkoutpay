@extends('layouts.business')

@section('title', 'Invoices')
@section('page-title', 'Invoices')

@section('content')
<div class="space-y-4 lg:space-y-6 pb-20 lg:pb-0">
    <!-- Header Actions -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-xl lg:text-2xl font-bold text-gray-900">All Invoices</h2>
            <p class="text-sm text-gray-600 mt-1">Create and manage professional invoices</p>
        </div>
        <a href="{{ route('business.invoices.create') }}" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium flex items-center gap-2">
            <i class="fas fa-plus"></i>
            Create Invoice
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Invoices -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs lg:text-sm text-gray-600 mb-1">Total Invoices</p>
                    <h3 class="text-xl lg:text-2xl font-bold text-gray-900">{{ number_format($stats['total']) }}</h3>
                </div>
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-file-invoice text-blue-600 text-lg lg:text-xl"></i>
                </div>
            </div>
            <div class="mt-3 lg:mt-4 flex items-center text-xs lg:text-sm">
                <span class="text-gray-600">This Month: {{ number_format($stats['this_month_total']) }}</span>
            </div>
        </div>

        <!-- Paid Invoices -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs lg:text-sm text-gray-600 mb-1">Paid Invoices</p>
                    <h3 class="text-xl lg:text-2xl font-bold text-green-600">{{ number_format($stats['paid']) }}</h3>
                </div>
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-lg lg:text-xl"></i>
                </div>
            </div>
            <div class="mt-3 lg:mt-4 flex items-center text-xs lg:text-sm">
                <span class="text-gray-600">Paid: ₦{{ number_format($stats['paid_amount'] ?? 0, 2) }}</span>
            </div>
        </div>

        <!-- Pending Invoices -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs lg:text-sm text-gray-600 mb-1">Pending</p>
                    <h3 class="text-xl lg:text-2xl font-bold text-yellow-600">{{ number_format($stats['sent'] + $stats['viewed']) }}</h3>
                </div>
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 text-lg lg:text-xl"></i>
                </div>
            </div>
            <div class="mt-3 lg:mt-4 flex items-center text-xs lg:text-sm">
                <span class="text-gray-600">Pending: ₦{{ number_format($stats['pending_amount'] ?? 0, 2) }}</span>
            </div>
        </div>

        <!-- Total Amount -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs lg:text-sm text-gray-600 mb-1">Total Amount</p>
                    <h3 class="text-xl lg:text-2xl font-bold text-gray-900">₦{{ number_format($stats['total_amount'] ?? 0, 2) }}</h3>
                </div>
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-purple-600 text-lg lg:text-xl"></i>
                </div>
            </div>
            <div class="mt-3 lg:mt-4 flex items-center text-xs lg:text-sm">
                <span class="text-gray-600">This Month: ₦{{ number_format($stats['this_month_amount'] ?? 0, 2) }}</span>
            </div>
        </div>
    </div>

    <!-- Status Breakdown -->
    <div class="grid grid-cols-3 sm:grid-cols-6 gap-3 lg:gap-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 lg:p-4">
            <div class="text-center">
                <p class="text-xs text-gray-600 mb-1">Draft</p>
                <p class="text-base lg:text-lg font-semibold text-gray-900">{{ number_format($stats['draft']) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 lg:p-4">
            <div class="text-center">
                <p class="text-xs text-gray-600 mb-1">Sent</p>
                <p class="text-base lg:text-lg font-semibold text-blue-600">{{ number_format($stats['sent']) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 lg:p-4">
            <div class="text-center">
                <p class="text-xs text-gray-600 mb-1">Viewed</p>
                <p class="text-base lg:text-lg font-semibold text-purple-600">{{ number_format($stats['viewed']) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 lg:p-4">
            <div class="text-center">
                <p class="text-xs text-gray-600 mb-1">Paid</p>
                <p class="text-base lg:text-lg font-semibold text-green-600">{{ number_format($stats['paid']) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 lg:p-4">
            <div class="text-center">
                <p class="text-xs text-gray-600 mb-1">Overdue</p>
                <p class="text-base lg:text-lg font-semibold text-red-600">{{ number_format($stats['overdue']) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 lg:p-4">
            <div class="text-center">
                <p class="text-xs text-gray-600 mb-1">Cancelled</p>
                <p class="text-base lg:text-lg font-semibold text-gray-600">{{ number_format($stats['cancelled']) }}</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6">
        <form method="GET" action="{{ route('business.invoices.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs lg:text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Invoice #, Client Name, Email" 
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
            </div>
            <div>
                <label class="block text-xs lg:text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    <option value="">All</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Sent</option>
                    <option value="viewed" {{ request('status') === 'viewed' ? 'selected' : '' }}>Viewed</option>
                    <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>Overdue</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            <div>
                <label class="block text-xs lg:text-sm font-medium text-gray-700 mb-1">From Date</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" 
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
            </div>
            <div>
                <label class="block text-xs lg:text-sm font-medium text-gray-700 mb-1">To Date</label>
                <div class="flex gap-2">
                    <input type="date" name="date_to" value="{{ request('date_to') }}" 
                        class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Invoices List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <!-- Desktop Table View -->
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
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
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $invoice->invoice_date->format('M d, Y') }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('business.invoices.show', $invoice) }}" class="text-sm text-primary hover:underline">
                                    View
                                </a>
                                @if(!$invoice->isPaid())
                                    <a href="{{ route('business.invoices.edit', $invoice) }}" class="text-sm text-gray-600 hover:text-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-file-invoice text-gray-400 text-4xl mb-3"></i>
                                <p class="text-sm text-gray-500">No invoices found</p>
                                <a href="{{ route('business.invoices.create') }}" class="mt-4 text-sm text-primary hover:underline">
                                    Create your first invoice
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="lg:hidden divide-y divide-gray-200">
            @forelse($invoices as $invoice)
            <div class="p-4">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <div class="text-sm font-medium text-gray-900">{{ $invoice->invoice_number }}</div>
                        <div class="text-xs text-gray-500 mt-1">{{ $invoice->client_name }}</div>
                    </div>
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
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="font-semibold text-gray-900">{{ $invoice->currency }} {{ number_format($invoice->total_amount, 2) }}</span>
                    <a href="{{ route('business.invoices.show', $invoice) }}" class="text-primary hover:underline">
                        View <i class="fas fa-chevron-right text-xs"></i>
                    </a>
                </div>
            </div>
            @empty
            <div class="p-12 text-center">
                <i class="fas fa-file-invoice text-gray-400 text-4xl mb-3"></i>
                <p class="text-sm text-gray-500 mb-4">No invoices found</p>
                <a href="{{ route('business.invoices.create') }}" class="text-sm text-primary hover:underline">
                    Create your first invoice
                </a>
            </div>
            @endforelse
        </div>
    </div>

    <!-- Pagination -->
    @if($invoices->hasPages())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        {{ $invoices->links() }}
    </div>
    @endif
</div>
@endsection

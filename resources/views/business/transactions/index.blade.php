@extends('layouts.business')

@section('title', 'Transactions')
@section('page-title', 'Transactions')

@section('content')
<div class="space-y-4 lg:space-y-6 pb-20 lg:pb-0">
    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6">
        <form method="GET" action="{{ route('business.transactions.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs lg:text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Transaction ID" 
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
            </div>
            <div>
                <label class="block text-xs lg:text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    <option value="">All</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
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

    <!-- Transactions List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <!-- Desktop Table View -->
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($transactions as $transaction)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $transaction->transaction_id }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">₦{{ number_format($transaction->amount, 2) }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $transaction->payer_name ?? '-' }}
                        </td>
                        <td class="px-6 py-4">
                            @if($transaction->status === 'approved')
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                            @elseif($transaction->status === 'pending')
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $transaction->created_at->format('M d, Y H:i') }}</td>
                        <td class="px-6 py-4">
                            <a href="{{ route('business.transactions.show', $transaction) }}" class="text-sm text-primary hover:underline">
                                View
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No transactions found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Mobile Card View -->
        <div class="lg:hidden divide-y divide-gray-200">
            @forelse($transactions as $transaction)
            <a href="{{ route('business.transactions.show', $transaction) }}" class="block p-4 hover:bg-gray-50 transition-colors">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate mb-1">{{ Str::limit($transaction->transaction_id, 20) }}</p>
                        <p class="text-xs text-gray-500">{{ $transaction->created_at->format('M d, Y H:i') }}</p>
                    </div>
                    <div class="ml-3 text-right">
                        @if($transaction->status === 'approved')
                            <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                        @elseif($transaction->status === 'pending')
                            <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                        @else
                            <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-600">Amount</p>
                        <p class="text-base font-bold text-gray-900">₦{{ number_format($transaction->amount, 2) }}</p>
                    </div>
                    @if($transaction->payer_name)
                    <div class="text-right">
                        <p class="text-xs text-gray-600">Payer</p>
                        <p class="text-sm font-medium text-gray-900 truncate max-w-[120px]">{{ $transaction->payer_name }}</p>
                    </div>
                    @endif
                    <div class="ml-auto">
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </div>
                </div>
            </a>
            @empty
            <div class="p-8 text-center">
                <i class="fas fa-exchange-alt text-gray-300 text-4xl mb-4"></i>
                <p class="text-sm text-gray-500">No transactions found</p>
            </div>
            @endforelse
        </div>
        
        <!-- Pagination -->
        <div class="px-4 lg:px-6 py-4 border-t border-gray-200">
            {{ $transactions->links() }}
        </div>
    </div>
</div>
@endsection

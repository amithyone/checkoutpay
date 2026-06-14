@extends('layouts.business')

@section('title', 'Transactions')
@section('page-title', 'Transactions')

@section('content')
<div class="space-y-4 lg:space-y-6 pb-20 lg:pb-0">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6">
        <form method="GET" action="{{ route('business.transactions.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs lg:text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Transaction ID or loan ref"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
            </div>
            <div>
                <label class="block text-xs lg:text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    <option value="">All</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved / Completed</option>
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

    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Counterparty</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($transactions as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $row['reference'] }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            @if($row['kind'] === 'loan_repayment')
                                <span class="inline-flex items-center gap-1">
                                    <i class="fas fa-hand-holding-usd text-violet-600"></i>
                                    Loan repayment
                                    @if($row['direction'] === 'out')
                                        <span class="text-xs text-red-600">(paid)</span>
                                    @else
                                        <span class="text-xs text-green-600">(received)</span>
                                    @endif
                                </span>
                            @else
                                Payment
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm font-semibold {{ $row['direction'] === 'out' ? 'text-red-700' : 'text-gray-900' }}">
                            @if($row['direction'] === 'out')−@endif₦{{ number_format($row['amount'], 2) }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $row['counterparty_label'] ?? ($row['description'] ? Str::limit($row['description'], 40) : '—') }}
                        </td>
                        <td class="px-6 py-4">
                            @if(in_array($row['status'], ['approved', 'completed'], true))
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Completed</span>
                            @elseif($row['status'] === 'pending')
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $row['occurred_at']->format('M d, Y H:i') }}</td>
                        <td class="px-6 py-4">
                            @if($row['kind'] === 'loan_repayment')
                                <a href="{{ route('business.transactions.loan.show', $row['loan_transaction']) }}" class="text-sm text-primary hover:underline">View</a>
                            @else
                                <a href="{{ route('business.transactions.show', $row['payment']) }}" class="text-sm text-primary hover:underline">View</a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No transactions found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="lg:hidden divide-y divide-gray-200">
            @forelse($transactions as $row)
            @php
                $rowUrl = $row['kind'] === 'loan_repayment'
                    ? route('business.transactions.loan.show', $row['loan_transaction'])
                    : route('business.transactions.show', $row['payment']);
            @endphp
            <a href="{{ $rowUrl }}" class="block p-4 hover:bg-gray-50 transition-colors">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate mb-1">{{ Str::limit($row['reference'], 24) }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $row['kind'] === 'loan_repayment' ? 'Loan repayment' : 'Payment' }}
                            · {{ $row['occurred_at']->format('M d, Y H:i') }}
                        </p>
                    </div>
                    <div class="ml-3 text-right">
                        @if(in_array($row['status'], ['approved', 'completed'], true))
                            <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Completed</span>
                        @elseif($row['status'] === 'pending')
                            <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                        @else
                            <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-600">Amount</p>
                        <p class="text-base font-bold {{ $row['direction'] === 'out' ? 'text-red-700' : 'text-gray-900' }}">
                            @if($row['direction'] === 'out')−@endif₦{{ number_format($row['amount'], 2) }}
                        </p>
                    </div>
                    <i class="fas fa-chevron-right text-gray-400"></i>
                </div>
            </a>
            @empty
            <div class="p-8 text-center">
                <i class="fas fa-exchange-alt text-gray-300 text-4xl mb-4"></i>
                <p class="text-sm text-gray-500">No transactions found</p>
            </div>
            @endforelse
        </div>

        <div class="px-4 lg:px-6 py-4 border-t border-gray-200">
            {{ $transactions->links() }}
        </div>
    </div>
</div>
@endsection

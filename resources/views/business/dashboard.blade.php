@extends('layouts.business')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-4 lg:space-y-6 pb-20 lg:pb-0">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
        <!-- Total Revenue -->
        <div class="bg-white rounded-xl shadow-sm p-5 lg:p-6 border border-gray-200 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="flex-1 min-w-0">
                    <p class="text-xs lg:text-sm text-gray-600 mb-1">Total Revenue</p>
                    <h3 class="text-xl lg:text-2xl font-bold text-gray-900 truncate">₦{{ number_format($stats['total_revenue'], 2) }}</h3>
                </div>
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0 ml-3">
                    <i class="fas fa-money-bill-wave text-green-600 text-lg lg:text-xl"></i>
                </div>
            </div>
            <div class="pt-3 border-t border-gray-100">
                <span class="text-xs text-gray-600">From {{ number_format($stats['approved_payments']) }} approved</span>
            </div>
        </div>

        <!-- Current Balance -->
        <div class="bg-white rounded-xl shadow-sm p-5 lg:p-6 border border-gray-200 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="flex-1 min-w-0">
                    <p class="text-xs lg:text-sm text-gray-600 mb-1">Current Balance</p>
                    <h3 class="text-xl lg:text-2xl font-bold text-gray-900 truncate">₦{{ number_format($stats['balance'], 2) }}</h3>
                </div>
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0 ml-3">
                    <i class="fas fa-wallet text-blue-600 text-lg lg:text-xl"></i>
                </div>
            </div>
            <div class="pt-3 border-t border-gray-100">
                <a href="{{ route('business.withdrawals.create') }}" class="text-xs text-primary hover:underline inline-flex items-center">
                    Request Withdrawal <i class="fas fa-arrow-right ml-1 text-xs"></i>
                </a>
            </div>
        </div>

        <!-- Total Transactions -->
        <div class="bg-white rounded-xl shadow-sm p-5 lg:p-6 border border-gray-200 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="flex-1 min-w-0">
                    <p class="text-xs lg:text-sm text-gray-600 mb-1">Total Transactions</p>
                    <h3 class="text-xl lg:text-2xl font-bold text-gray-900">{{ number_format($stats['total_payments']) }}</h3>
                </div>
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0 ml-3">
                    <i class="fas fa-exchange-alt text-purple-600 text-lg lg:text-xl"></i>
                </div>
            </div>
            <div class="pt-3 border-t border-gray-100">
                <div class="flex items-center text-xs space-x-2">
                    <span class="text-green-600">Approved: {{ $stats['approved_payments'] }}</span>
                    <span class="text-gray-300">•</span>
                    <span class="text-yellow-600">Pending: {{ $stats['pending_payments'] }}</span>
                </div>
            </div>
        </div>

        <!-- Pending Withdrawals -->
        <div class="bg-white rounded-xl shadow-sm p-5 lg:p-6 border border-gray-200 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="flex-1 min-w-0">
                    <p class="text-xs lg:text-sm text-gray-600 mb-1">Pending Withdrawals</p>
                    <h3 class="text-xl lg:text-2xl font-bold text-gray-900">{{ number_format($stats['pending_withdrawals']) }}</h3>
                </div>
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-yellow-100 rounded-lg flex items-center justify-center flex-shrink-0 ml-3">
                    <i class="fas fa-hand-holding-usd text-yellow-600 text-lg lg:text-xl"></i>
                </div>
            </div>
            <div class="pt-3 border-t border-gray-100">
                <a href="{{ route('business.withdrawals.index', ['status' => 'pending']) }}" class="text-xs text-primary hover:underline inline-flex items-center">
                    View All <i class="fas fa-arrow-right ml-1 text-xs"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
        <!-- Recent Transactions -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="p-4 lg:p-6 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base lg:text-lg font-semibold text-gray-900">Recent Transactions</h3>
                <a href="{{ route('business.transactions.index') }}" class="text-xs lg:text-sm text-primary hover:underline">View All</a>
            </div>
            <!-- Desktop Table View -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($recentPayments as $payment)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <a href="{{ route('business.transactions.show', $payment) }}" class="text-sm font-medium text-primary hover:underline">
                                    {{ Str::limit($payment->transaction_id, 20) }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">₦{{ number_format($payment->amount, 2) }}</td>
                            <td class="px-6 py-4">
                                @if($payment->status === 'approved')
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                                @elseif($payment->status === 'pending')
                                    <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $payment->created_at->format('M d, Y') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No transactions found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <!-- Mobile Card View -->
            <div class="lg:hidden divide-y divide-gray-200">
                @forelse($recentPayments as $payment)
                <a href="{{ route('business.transactions.show', $payment) }}" class="block p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ Str::limit($payment->transaction_id, 25) }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ $payment->created_at->format('M d, Y') }}</p>
                        </div>
                        <div class="ml-4 text-right">
                            <p class="text-base font-semibold text-gray-900">₦{{ number_format($payment->amount, 2) }}</p>
                            <div class="mt-1">
                                @if($payment->status === 'approved')
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                                @elseif($payment->status === 'pending')
                                    <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </a>
                @empty
                <div class="p-4 text-center text-sm text-gray-500">No transactions found</div>
                @endforelse
            </div>
        </div>

        <!-- Recent Withdrawals -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="p-4 lg:p-6 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base lg:text-lg font-semibold text-gray-900">Recent Withdrawals</h3>
                <a href="{{ route('business.withdrawals.index') }}" class="text-xs lg:text-sm text-primary hover:underline">View All</a>
            </div>
            <!-- Desktop Table View -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($recentWithdrawals as $withdrawal)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900">₦{{ number_format($withdrawal->amount, 2) }}</td>
                            <td class="px-6 py-4">
                                @if($withdrawal->status === 'approved')
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                                @elseif($withdrawal->status === 'pending')
                                    <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                                @elseif($withdrawal->status === 'rejected')
                                    <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">Processed</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $withdrawal->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4">
                                <a href="{{ route('business.withdrawals.show', $withdrawal) }}" class="text-sm text-primary hover:underline">View</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No withdrawals found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <!-- Mobile Card View -->
            <div class="lg:hidden divide-y divide-gray-200">
                @forelse($recentWithdrawals as $withdrawal)
                <a href="{{ route('business.withdrawals.show', $withdrawal) }}" class="block p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-base font-semibold text-gray-900">₦{{ number_format($withdrawal->amount, 2) }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ $withdrawal->created_at->format('M d, Y') }}</p>
                        </div>
                        <div class="ml-4">
                            @if($withdrawal->status === 'approved')
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                            @elseif($withdrawal->status === 'pending')
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                            @elseif($withdrawal->status === 'rejected')
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">Processed</span>
                            @endif
                        </div>
                    </div>
                </a>
                @empty
                <div class="p-4 text-center text-sm text-gray-500">No withdrawals found</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

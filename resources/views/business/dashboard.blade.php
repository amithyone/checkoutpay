@extends('layouts.business')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-4 lg:space-y-6 pb-20 lg:pb-0">
    <!-- Business ID Card -->
    <div class="bg-gradient-to-r from-primary to-primary/90 rounded-xl shadow-sm p-5 lg:p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs lg:text-sm text-white/80 mb-1">Business ID</p>
                <h3 class="text-xl lg:text-2xl font-bold font-mono">{{ auth('business')->user()->business_id ?? auth('business')->user()->id }}</h3>
                <p class="text-xs text-white/70 mt-2">Use this ID for API integrations and support requests</p>
            </div>
            <div class="w-12 h-12 lg:w-16 lg:h-16 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="fas fa-id-card text-white text-xl lg:text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
        <!-- Daily Revenue -->
        <div class="bg-white rounded-xl shadow-sm p-5 lg:p-6 border border-gray-200 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="flex-1 min-w-0">
                    <p class="text-xs lg:text-sm text-gray-600 mb-1">Daily Revenue</p>
                    <h3 class="text-xl lg:text-2xl font-bold text-gray-900 truncate">₦{{ number_format($stats['today_revenue'] ?? 0, 2) }}</h3>
                </div>
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0 ml-3">
                    <i class="fas fa-money-bill-wave text-green-600 text-lg lg:text-xl"></i>
                </div>
            </div>
            <div class="pt-3 border-t border-gray-100">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-600">Monthly: ₦{{ number_format($stats['monthly_revenue'] ?? 0, 2) }}</span>
                    <span class="text-gray-600">Yearly: ₦{{ number_format($stats['yearly_revenue'] ?? 0, 2) }}</span>
                </div>
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

    <!-- Website Revenue Breakdown -->
    @if(count($websiteStats) > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-4 lg:p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-base lg:text-lg font-semibold text-gray-900">
                <i class="fas fa-globe mr-2 text-primary"></i> Website Revenue Breakdown
            </h3>
            <a href="{{ route('business.websites.index') }}" class="text-xs lg:text-sm text-primary hover:underline">Manage Websites</a>
        </div>
        <div class="p-4 lg:p-6">
            <div class="space-y-4">
                @foreach($websiteStats as $websiteStat)
                <div class="border border-gray-200 rounded-lg p-3 sm:p-4 hover:bg-gray-50 overflow-hidden">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-1">
                                <a href="{{ $websiteStat['website']->website_url }}" target="_blank" class="text-primary hover:underline font-medium text-xs sm:text-sm truncate">
                                    {{ parse_url($websiteStat['website']->website_url, PHP_URL_HOST) }}
                                    <i class="fas fa-external-link-alt text-xs ml-1"></i>
                                </a>
                                @if($websiteStat['website']->is_approved)
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full self-start">
                                        <i class="fas fa-check-circle mr-1"></i> Approved
                                    </span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full self-start">
                                        <i class="fas fa-clock mr-1"></i> Pending
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="text-left sm:text-right min-w-0">
                            <p class="text-base sm:text-lg font-bold text-gray-900 break-words leading-tight">₦{{ number_format($websiteStat['total_revenue'], 2) }}</p>
                            <p class="text-xs text-gray-500">Total: {{ $websiteStat['total_payments'] }} payments</p>
                        </div>
                    </div>
                    
                    <!-- Daily and Monthly Revenue Breakdown -->
                    <div class="grid grid-cols-2 gap-3 sm:gap-4 mt-4 pt-4 border-t border-gray-100">
                        <div class="bg-blue-50 rounded-lg p-3 min-w-0 overflow-hidden">
                            <p class="text-xs text-gray-600 mb-1">Today</p>
                            <p class="text-sm sm:text-base font-bold text-blue-900 break-words leading-tight">₦{{ number_format($websiteStat['today_revenue'], 2) }}</p>
                            <p class="text-xs text-gray-500 mt-1">Monthly: ₦{{ number_format($websiteStat['monthly_revenue'] ?? 0, 2) }} • Yearly: ₦{{ number_format($websiteStat['yearly_revenue'] ?? 0, 2) }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ $websiteStat['today_payments'] }} payment{{ $websiteStat['today_payments'] != 1 ? 's' : '' }}</p>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-3 min-w-0 overflow-hidden">
                            <p class="text-xs text-gray-600 mb-1">This Month</p>
                            <p class="text-sm sm:text-base font-bold text-purple-900 break-words leading-tight">₦{{ number_format($websiteStat['monthly_revenue'], 2) }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ $websiteStat['monthly_payments'] }} payment{{ $websiteStat['monthly_payments'] != 1 ? 's' : '' }}</p>
                        </div>
                    </div>
                    @if(isset($websiteStat['yearly_revenue']))
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <div class="bg-green-50 rounded-lg p-3">
                            <p class="text-xs text-gray-600 mb-1">This Year</p>
                            <p class="text-sm sm:text-base font-bold text-green-900">₦{{ number_format($websiteStat['yearly_revenue'], 2) }}</p>
                        </div>
                    </div>
                    @endif
                    </div>
                    
                    <div class="flex flex-wrap items-center gap-3 sm:gap-4 text-xs text-gray-600 mt-3">
                        <span><strong>{{ number_format($websiteStat['total_payments']) }}</strong> approved</span>
                        @if($websiteStat['pending_payments'] > 0)
                            <span class="text-yellow-600"><strong>{{ number_format($websiteStat['pending_payments']) }}</strong> pending</span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
        <!-- Recent Transactions -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="p-4 lg:p-6 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base lg:text-lg font-semibold text-gray-900">Recent Transactions</h3>
                <a href="{{ route('business.transactions.index') }}" class="text-xs lg:text-sm text-primary hover:underline">View All</a>
            </div>
            <!-- Desktop Table View -->
            <div class="hidden lg:block overflow-x-auto -mx-4 lg:mx-0">
                <div class="inline-block min-w-full align-middle px-4 lg:px-0">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID</th>
                                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Website</th>
                                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase min-w-[100px]">Amount</th>
                                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($recentPayments as $payment)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 lg:px-6 py-4">
                                    <a href="{{ route('business.transactions.show', $payment) }}" class="text-xs sm:text-sm font-medium text-primary hover:underline break-words">
                                        {{ Str::limit($payment->transaction_id, 20) }}
                                    </a>
                                </td>
                                <td class="px-4 lg:px-6 py-4 text-xs sm:text-sm text-gray-600">
                                    @if($payment->website)
                                        <span class="text-xs truncate block max-w-[150px]" title="{{ $payment->website->website_url }}">
                                            {{ parse_url($payment->website->website_url, PHP_URL_HOST) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-xs">N/A</span>
                                    @endif
                                </td>
                                <td class="px-4 lg:px-6 py-4 text-xs sm:text-sm text-gray-900 break-words min-w-0">₦{{ number_format($payment->amount, 2) }}</td>
                                <td class="px-4 lg:px-6 py-4">
                                    @if($payment->status === 'approved')
                                        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                                    @elseif($payment->status === 'pending')
                                        <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                                    @endif
                                </td>
                                <td class="px-4 lg:px-6 py-4 text-xs sm:text-sm text-gray-500 whitespace-nowrap">{{ $payment->created_at->format('M d, Y') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="px-4 lg:px-6 py-4 text-center text-xs sm:text-sm text-gray-500">No transactions found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Mobile Card View -->
            <div class="lg:hidden divide-y divide-gray-200">
                @forelse($recentPayments as $payment)
                <a href="{{ route('business.transactions.show', $payment) }}" class="block p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ Str::limit($payment->transaction_id, 25) }}</p>
                            <p class="text-xs text-gray-500 mt-1 break-words">
                                @if($payment->website)
                                    {{ parse_url($payment->website->website_url, PHP_URL_HOST) }} • 
                                @endif
                                {{ $payment->created_at->format('M d, Y') }}
                            </p>
                        </div>
                        <div class="ml-4 text-right flex-shrink-0 min-w-0">
                            <p class="text-sm sm:text-base font-semibold text-gray-900 break-words leading-tight">₦{{ number_format($payment->amount, 2) }}</p>
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
                <div class="p-4 text-center text-xs sm:text-sm text-gray-500">No transactions found</div>
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
            <div class="hidden lg:block overflow-x-auto -mx-4 lg:mx-0">
                <div class="inline-block min-w-full align-middle px-4 lg:px-0">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase min-w-[100px]">Amount</th>
                                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($recentWithdrawals as $withdrawal)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 lg:px-6 py-4 text-xs sm:text-sm text-gray-900 break-words min-w-0">₦{{ number_format($withdrawal->amount, 2) }}</td>
                                <td class="px-4 lg:px-6 py-4">
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
                                <td class="px-4 lg:px-6 py-4 text-xs sm:text-sm text-gray-500 whitespace-nowrap">{{ $withdrawal->created_at->format('M d, Y') }}</td>
                                <td class="px-4 lg:px-6 py-4">
                                    <a href="{{ route('business.withdrawals.show', $withdrawal) }}" class="text-xs sm:text-sm text-primary hover:underline">View</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="px-4 lg:px-6 py-4 text-center text-xs sm:text-sm text-gray-500">No withdrawals found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Mobile Card View -->
            <div class="lg:hidden divide-y divide-gray-200">
                @forelse($recentWithdrawals as $withdrawal)
                <a href="{{ route('business.withdrawals.show', $withdrawal) }}" class="block p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm sm:text-base font-semibold text-gray-900 break-words leading-tight">₦{{ number_format($withdrawal->amount, 2) }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ $withdrawal->created_at->format('M d, Y') }}</p>
                        </div>
                        <div class="ml-4 flex-shrink-0">
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
                <div class="p-4 text-center text-xs sm:text-sm text-gray-500">No withdrawals found</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

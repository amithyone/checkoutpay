@extends('layouts.business')

@section('title', 'Statistics')
@section('page-title', 'Statistics')

@section('content')
<div class="space-y-6">
    <!-- Date Range Filter -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form method="GET" action="{{ route('business.statistics.index') }}" class="flex gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
            </div>
            <div>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Overall Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Transactions</p>
                    <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_transactions']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exchange-alt text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Revenue</p>
                    <h3 class="text-2xl font-bold text-gray-900">₦{{ number_format($stats['total_revenue'], 2) }}</h3>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Average Transaction</p>
                    <h3 class="text-2xl font-bold text-gray-900">₦{{ number_format($stats['average_transaction'] ?? 0, 2) }}</h3>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Approval Rate</p>
                    <h3 class="text-2xl font-bold text-gray-900">
                        {{ $stats['total_transactions'] > 0 ? number_format(($stats['total_approved'] / $stats['total_transactions']) * 100, 1) : 0 }}%
                    </h3>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-percentage text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Status Breakdown</h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-green-500 rounded-full mr-3"></div>
                        <span class="text-sm text-gray-700">Approved</span>
                    </div>
                    <span class="text-sm font-medium text-gray-900">{{ $statusBreakdown['approved'] ?? 0 }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-yellow-500 rounded-full mr-3"></div>
                        <span class="text-sm text-gray-700">Pending</span>
                    </div>
                    <span class="text-sm font-medium text-gray-900">{{ $statusBreakdown['pending'] ?? 0 }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-red-500 rounded-full mr-3"></div>
                        <span class="text-sm text-gray-700">Rejected</span>
                    </div>
                    <span class="text-sm font-medium text-gray-900">{{ $statusBreakdown['rejected'] ?? 0 }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Monthly Revenue</h3>
            <div class="space-y-3">
                @forelse($monthlyRevenue as $month)
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-700">
                        {{ \Carbon\Carbon::create($month->year, $month->month, 1)->format('M Y') }}
                    </span>
                    <div class="flex items-center gap-4">
                        <span class="text-sm font-medium text-gray-900">₦{{ number_format($month->revenue, 2) }}</span>
                        <span class="text-xs text-gray-500">({{ $month->count }} transactions)</span>
                    </div>
                </div>
                @empty
                <p class="text-sm text-gray-500">No revenue data available</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Daily Statistics Chart -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Daily Statistics</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transactions</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Approved</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($dailyStats as $stat)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-900">{{ \Carbon\Carbon::parse($stat->date)->format('M d, Y') }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $stat->count }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $stat->approved_count }}</td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">₦{{ number_format($stat->revenue, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No data available for selected period</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

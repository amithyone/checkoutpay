@extends('layouts.business')

@section('title', 'Statistics')
@section('page-title', 'Statistics')

@section('content')
<div class="space-y-6">
    <!-- Period Selector and Date Range Filter -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-end">
            <!-- Period Selector -->
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">View Period</label>
                <div class="flex gap-2">
                    <a href="{{ route('business.statistics.index', ['period' => 'daily'] + request()->except('period')) }}" 
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $period === 'daily' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        Daily
                    </a>
                    <a href="{{ route('business.statistics.index', ['period' => 'monthly'] + request()->except('period')) }}" 
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $period === 'monthly' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        Monthly
                    </a>
                    <a href="{{ route('business.statistics.index', ['period' => 'yearly'] + request()->except('period')) }}" 
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $period === 'yearly' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        Yearly
                    </a>
                </div>
            </div>
            
            <!-- Date Range -->
            <form method="GET" action="{{ route('business.statistics.index') }}" class="flex gap-4 items-end w-full lg:w-auto">
                <input type="hidden" name="period" value="{{ $period }}">
                <div class="flex-1 lg:flex-none">
                    <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm">
                </div>
                <div class="flex-1 lg:flex-none">
                    <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm">
                </div>
                <div>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Overall Statistics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6 border border-gray-200 min-w-0 overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs sm:text-sm text-gray-600 mb-1">Total Transactions</p>
                    <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 break-words">{{ number_format($stats['total_transactions']) }}</h3>
                </div>
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0 ml-2 sm:ml-3">
                    <i class="fas fa-exchange-alt text-blue-600 text-base sm:text-lg md:text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6 border border-gray-200 min-w-0 overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs sm:text-sm text-gray-600 mb-1">Total Revenue</p>
                    <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 break-words leading-tight">₦{{ number_format($stats['total_revenue'], 2) }}</h3>
                </div>
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0 ml-2 sm:ml-3">
                    <i class="fas fa-money-bill-wave text-green-600 text-base sm:text-lg md:text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6 border border-gray-200 min-w-0 overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs sm:text-sm text-gray-600 mb-1">Average Transaction</p>
                    <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 break-words leading-tight">₦{{ number_format($stats['average_transaction'] ?? 0, 2) }}</h3>
                </div>
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0 ml-2 sm:ml-3">
                    <i class="fas fa-chart-line text-purple-600 text-base sm:text-lg md:text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6 border border-gray-200 min-w-0 overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs sm:text-sm text-gray-600 mb-1">Approval Rate</p>
                    <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 break-words">
                        {{ $stats['total_transactions'] > 0 ? number_format(($stats['total_approved'] / $stats['total_transactions']) * 100, 1) : 0 }}%
                    </h3>
                </div>
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-yellow-100 rounded-lg flex items-center justify-center flex-shrink-0 ml-2 sm:ml-3">
                    <i class="fas fa-percentage text-yellow-600 text-base sm:text-lg md:text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Summary (Today/Monthly/Yearly) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
        <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-lg shadow-sm p-4 sm:p-6 border-2 border-green-200 min-w-0 overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs sm:text-sm text-gray-600 mb-1">Today's Revenue</p>
                    <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 break-words leading-tight">₦{{ number_format($stats['today_revenue'] ?? 0, 2) }}</h3>
                    <p class="text-xs text-gray-500 mt-1">From actual transactions</p>
                </div>
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0 ml-2 sm:ml-3">
                    <i class="fas fa-calendar-day text-green-600 text-base sm:text-lg md:text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg shadow-sm p-4 sm:p-6 border-2 border-blue-200 min-w-0 overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs sm:text-sm text-gray-600 mb-1">Monthly Revenue</p>
                    <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 break-words leading-tight">₦{{ number_format($stats['monthly_revenue'] ?? 0, 2) }}</h3>
                    <p class="text-xs text-gray-500 mt-1">From actual transactions</p>
                </div>
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0 ml-2 sm:ml-3">
                    <i class="fas fa-calendar-alt text-blue-600 text-base sm:text-lg md:text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-lg shadow-sm p-4 sm:p-6 border-2 border-purple-200 min-w-0 overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs sm:text-sm text-gray-600 mb-1">Yearly Revenue</p>
                    <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 break-words leading-tight">₦{{ number_format($stats['yearly_revenue'] ?? 0, 2) }}</h3>
                    <p class="text-xs text-gray-500 mt-1">From actual transactions</p>
                </div>
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0 ml-2 sm:ml-3">
                    <i class="fas fa-calendar text-purple-600 text-base sm:text-lg md:text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Website Performance Breakdown -->
    @if(count($websiteStats) > 0)
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-globe mr-2 text-primary"></i> Website Performance Breakdown
            </h3>
        </div>
        <div class="p-6">
            <div class="space-y-6">
                @foreach($websiteStats as $ws)
                <div class="border border-gray-200 rounded-lg p-4 sm:p-6 hover:bg-gray-50 overflow-hidden">
                    <!-- Website Header -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 mb-2">
                                <a href="{{ $ws['website']->website_url }}" target="_blank" class="text-primary hover:underline font-semibold text-sm sm:text-base md:text-lg truncate">
                                    {{ parse_url($ws['website']->website_url, PHP_URL_HOST) }}
                                    <i class="fas fa-external-link-alt text-xs ml-1"></i>
                                </a>
                                @if($ws['website']->is_approved)
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
                            <p class="text-base sm:text-lg md:text-xl lg:text-2xl font-bold text-gray-900 break-words">₦{{ number_format($ws['total_revenue'], 2) }}</p>
                            <p class="text-xs sm:text-sm text-gray-500">Total Revenue</p>
                        </div>
                    </div>

                    <!-- Quick Stats Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 mb-6">
                        <div class="bg-blue-50 rounded-lg p-3 sm:p-4 min-w-0 overflow-hidden">
                            <p class="text-xs text-gray-600 mb-1">Today</p>
                            <p class="text-sm sm:text-base md:text-lg font-bold text-blue-900 break-words leading-tight">₦{{ number_format($ws['today_revenue'], 2) }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ $ws['today_payments'] }} payments</p>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-3 sm:p-4 min-w-0 overflow-hidden">
                            <p class="text-xs text-gray-600 mb-1">This Month</p>
                            <p class="text-sm sm:text-base md:text-lg font-bold text-purple-900 break-words leading-tight">₦{{ number_format($ws['monthly_revenue'], 2) }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ $ws['monthly_payments'] }} payments</p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-3 sm:p-4 min-w-0 overflow-hidden">
                            <p class="text-xs text-gray-600 mb-1">This Year</p>
                            <p class="text-sm sm:text-base md:text-lg font-bold text-green-900 break-words leading-tight">₦{{ number_format($ws['yearly_revenue'], 2) }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ $ws['yearly_payments'] }} payments</p>
                        </div>
                        <div class="bg-orange-50 rounded-lg p-3 sm:p-4 min-w-0 overflow-hidden">
                            <p class="text-xs text-gray-600 mb-1">Approval Rate</p>
                            <p class="text-sm sm:text-base md:text-lg font-bold text-orange-900">{{ number_format($ws['approval_rate'], 1) }}%</p>
                            <p class="text-xs text-gray-500 mt-1 break-words leading-tight">Avg: ₦{{ number_format($ws['average_transaction'], 2) }}</p>
                        </div>
                    </div>

                    <!-- Status Breakdown -->
                    <div class="grid grid-cols-3 gap-3 sm:gap-4 mb-6">
                        <div class="text-center min-w-0">
                            <p class="text-lg sm:text-xl md:text-2xl font-bold text-green-600 break-words">{{ number_format($ws['approved_payments']) }}</p>
                            <p class="text-xs text-gray-600">Approved</p>
                        </div>
                        <div class="text-center min-w-0">
                            <p class="text-lg sm:text-xl md:text-2xl font-bold text-yellow-600 break-words">{{ number_format($ws['pending_payments']) }}</p>
                            <p class="text-xs text-gray-600">Pending</p>
                        </div>
                        <div class="text-center min-w-0">
                            <p class="text-lg sm:text-xl md:text-2xl font-bold text-red-600 break-words">{{ number_format($ws['rejected_payments']) }}</p>
                            <p class="text-xs text-gray-600">Rejected</p>
                        </div>
                    </div>

                    <!-- Period-Specific Stats Table -->
                    <div class="border-t border-gray-200 pt-4">
                        <h4 class="text-xs sm:text-sm font-semibold text-gray-900 mb-3">
                            {{ ucfirst($period) }} Performance ({{ $dateFrom }} to {{ $dateTo }})
                        </h4>
                        <div class="overflow-x-auto -mx-4 sm:mx-0">
                            <div class="inline-block min-w-full align-middle">
                                <table class="w-full text-xs sm:text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            @if($period === 'daily')
                                                <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                            @elseif($period === 'monthly')
                                                <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                                            @else
                                                <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Year</th>
                                            @endif
                                            <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                            <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Approved</th>
                                            <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Pending</th>
                                            <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rejected</th>
                                            <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase min-w-[100px]">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @php
                                            $periodStats = $period === 'daily' ? $ws['daily_stats'] : ($period === 'monthly' ? $ws['monthly_stats'] : $ws['yearly_stats']);
                                        @endphp
                                        @forelse($periodStats as $stat)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-2 sm:px-4 py-2 sm:py-3 text-gray-900 whitespace-nowrap">
                                                @if($period === 'daily')
                                                    {{ \Carbon\Carbon::parse($stat->date)->format('M d, Y') }}
                                                @elseif($period === 'monthly')
                                                    {{ \Carbon\Carbon::create($stat->year, $stat->month, 1)->format('M Y') }}
                                                @else
                                                    {{ $stat->year }}
                                                @endif
                                            </td>
                                            <td class="px-2 sm:px-4 py-2 sm:py-3 text-gray-600">{{ $stat->count }}</td>
                                            <td class="px-2 sm:px-4 py-2 sm:py-3 text-green-600 font-medium">{{ $stat->approved_count ?? 0 }}</td>
                                            <td class="px-2 sm:px-4 py-2 sm:py-3 text-yellow-600 font-medium">{{ $stat->pending_count ?? 0 }}</td>
                                            <td class="px-2 sm:px-4 py-2 sm:py-3 text-red-600 font-medium">{{ $stat->rejected_count ?? 0 }}</td>
                                            <td class="px-2 sm:px-4 py-2 sm:py-3 font-semibold text-gray-900 break-words min-w-0">₦{{ number_format($stat->revenue ?? 0, 2) }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="px-2 sm:px-4 py-4 text-center text-xs sm:text-sm text-gray-500">No data available for this period</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Overall Period Statistics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
        <!-- Status Breakdown -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4">Overall Status Breakdown</h3>
            <div class="space-y-3 sm:space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-3 h-3 sm:w-4 sm:h-4 bg-green-500 rounded-full mr-2 sm:mr-3 flex-shrink-0"></div>
                        <span class="text-xs sm:text-sm text-gray-700">Approved</span>
                    </div>
                    <span class="text-xs sm:text-sm font-medium text-gray-900 break-words">{{ number_format($statusBreakdown['approved'] ?? 0) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-3 h-3 sm:w-4 sm:h-4 bg-yellow-500 rounded-full mr-2 sm:mr-3 flex-shrink-0"></div>
                        <span class="text-xs sm:text-sm text-gray-700">Pending</span>
                    </div>
                    <span class="text-xs sm:text-sm font-medium text-gray-900 break-words">{{ number_format($statusBreakdown['pending'] ?? 0) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-3 h-3 sm:w-4 sm:h-4 bg-red-500 rounded-full mr-2 sm:mr-3 flex-shrink-0"></div>
                        <span class="text-xs sm:text-sm text-gray-700">Rejected</span>
                    </div>
                    <span class="text-xs sm:text-sm font-medium text-gray-900 break-words">{{ number_format($statusBreakdown['rejected'] ?? 0) }}</span>
                </div>
            </div>
        </div>

        <!-- Overall Period Performance -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4">Overall {{ ucfirst($period) }} Performance</h3>
            <div class="overflow-x-auto -mx-4 sm:mx-0">
                <div class="inline-block min-w-full align-middle px-4 sm:px-0">
                    <table class="w-full text-xs sm:text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                @if($period === 'daily')
                                    <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                @elseif($period === 'monthly')
                                    <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                                @else
                                    <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Year</th>
                                @endif
                                <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Transactions</th>
                                <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Approved</th>
                                <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase min-w-[100px]">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @php
                                $overallStats = $period === 'daily' ? $dailyStats : ($period === 'monthly' ? $monthlyStats : $yearlyStats);
                            @endphp
                            @forelse($overallStats as $stat)
                            <tr class="hover:bg-gray-50">
                                <td class="px-2 sm:px-4 py-2 sm:py-3 text-gray-900 whitespace-nowrap">
                                    @if($period === 'daily')
                                        {{ \Carbon\Carbon::parse($stat->date)->format('M d, Y') }}
                                    @elseif($period === 'monthly')
                                        {{ \Carbon\Carbon::create($stat->year, $stat->month, 1)->format('M Y') }}
                                    @else
                                        {{ $stat->year }}
                                    @endif
                                </td>
                                <td class="px-2 sm:px-4 py-2 sm:py-3 text-gray-600">{{ $stat->count }}</td>
                                <td class="px-2 sm:px-4 py-2 sm:py-3 text-green-600 font-medium">{{ $stat->approved_count ?? 0 }}</td>
                                <td class="px-2 sm:px-4 py-2 sm:py-3 font-semibold text-gray-900 break-words min-w-0">₦{{ number_format($stat->revenue ?? 0, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="px-2 sm:px-4 py-4 text-center text-xs sm:text-sm text-gray-500">No data available for selected period</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

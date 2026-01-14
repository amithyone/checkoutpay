@extends('layouts.admin')

@section('title', 'Statistics')
@section('page-title', 'Statistics Dashboard')

@section('content')
<div class="space-y-6">
    <!-- Period Selector -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">View Statistics</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.stats.index', ['period' => 'daily']) }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $period === 'daily' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    Daily
                </a>
                <a href="{{ route('admin.stats.index', ['period' => 'monthly']) }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $period === 'monthly' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    Monthly
                </a>
                <a href="{{ route('admin.stats.index', ['period' => 'yearly']) }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $period === 'yearly' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    Yearly
                </a>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Amount -->
        <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-lg shadow-sm p-6 border-2 border-green-200">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Amount</p>
                    <h3 class="text-3xl font-bold text-gray-900">₦{{ number_format($stats['summary']['total_amount'], 2) }}</h3>
                </div>
                <div class="w-16 h-16 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-green-600 text-2xl"></i>
                </div>
            </div>
            <div class="text-xs text-gray-500">
                @if($period === 'daily')
                    Last 30 days
                @elseif($period === 'monthly')
                    Last 12 months
                @else
                    Last 5 years
                @endif
            </div>
        </div>

        <!-- Total Transactions -->
        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg shadow-sm p-6 border-2 border-blue-200">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Transactions</p>
                    <h3 class="text-3xl font-bold text-gray-900">{{ number_format($stats['summary']['total_transactions']) }}</h3>
                </div>
                <div class="w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exchange-alt text-blue-600 text-2xl"></i>
                </div>
            </div>
            <div class="text-xs text-gray-500">
                {{ number_format($stats['summary']['approved_transactions']) }} approved
            </div>
        </div>

        <!-- Approved Transactions -->
        <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-lg shadow-sm p-6 border-2 border-purple-200">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Approved</p>
                    <h3 class="text-3xl font-bold text-gray-900">{{ number_format($stats['summary']['approved_transactions']) }}</h3>
                </div>
                <div class="w-16 h-16 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-purple-600 text-2xl"></i>
                </div>
            </div>
            <div class="text-xs text-gray-500">
                @if($stats['summary']['total_transactions'] > 0)
                    {{ round(($stats['summary']['approved_transactions'] / $stats['summary']['total_transactions']) * 100, 1) }}% approval rate
                @else
                    0% approval rate
                @endif
            </div>
        </div>

        <!-- Average -->
        <div class="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-lg shadow-sm p-6 border-2 border-yellow-200">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm text-gray-600 mb-1">
                        @if($period === 'daily')
                            Average Daily
                        @elseif($period === 'monthly')
                            Average Monthly
                        @else
                            Average Yearly
                        @endif
                    </p>
                    <h3 class="text-3xl font-bold text-gray-900">₦{{ number_format($stats['summary']['average_transaction'], 2) }}</h3>
                </div>
                <div class="w-16 h-16 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-chart-line text-yellow-600 text-2xl"></i>
                </div>
            </div>
            <div class="text-xs text-gray-500">
                Per transaction
            </div>
        </div>
    </div>

    <!-- Current Period Stats -->
    @if(isset($stats['today']) || isset($stats['current_month']) || isset($stats['current_year']))
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            @if($period === 'daily')
                Today's Performance
            @elseif($period === 'monthly')
                Current Month Performance
            @else
                Current Year Performance
            @endif
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @php
                $current = $stats['today'] ?? $stats['current_month'] ?? $stats['current_year'];
            @endphp
            <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-sm text-gray-600 mb-1">Amount</p>
                <p class="text-2xl font-bold text-gray-900">₦{{ number_format($current['amount'], 2) }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-sm text-gray-600 mb-1">Transactions</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($current['transactions']) }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-sm text-gray-600 mb-1">Approved</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($current['approved']) }}</p>
            </div>
        </div>
    </div>
    @endif

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Amount Chart -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Amount Received</h3>
            <div class="relative" style="height: 400px; max-height: 400px; overflow: hidden;">
                <canvas id="amountChart"></canvas>
            </div>
        </div>

        <!-- Transaction Count Chart -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Transaction Count</h3>
            <div class="relative" style="height: 400px; max-height: 400px; overflow: hidden;">
                <canvas id="countChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Businesses -->
    @if(isset($stats['top_businesses']) && $stats['top_businesses']->count() > 0)
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Top Businesses</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rank</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transactions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($stats['top_businesses'] as $index => $business)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <span class="text-sm font-medium text-gray-900">#{{ $index + 1 }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm font-medium text-gray-900">{{ $business->name }}</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">₦{{ number_format($business->total_amount, 2) }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ number_format($business->transaction_count) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Recent Payments -->
    @if(isset($stats['recent_payments']) && $stats['recent_payments']->count() > 0)
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Recent Payments</h3>
            <a href="{{ route('admin.payments.index') }}" class="text-sm text-primary hover:underline">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($stats['recent_payments'] as $payment)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.payments.show', $payment) }}" class="text-sm font-medium text-primary hover:underline">
                                {{ $payment->transaction_id }}
                            </a>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $payment->business->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">₦{{ number_format($payment->received_amount ?: $payment->amount, 2) }}</td>
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
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Amount Chart
const amountCtx = document.getElementById('amountChart').getContext('2d');
const amountChart = new Chart(amountCtx, {
    type: 'line',
    data: {
        labels: @json(array_column($stats['chart_data']['amounts']->toArray(), 'date')),
        datasets: [{
            label: 'Amount (₦)',
            data: @json(array_column($stats['chart_data']['amounts']->toArray(), 'amount')),
            borderColor: 'rgb(34, 197, 94)',
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 1.5,
        plugins: {
            legend: {
                display: true,
                position: 'top',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return '₦' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₦' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Count Chart
const countCtx = document.getElementById('countChart').getContext('2d');
const countChart = new Chart(countCtx, {
    type: 'bar',
    data: {
        labels: @json(array_column($stats['chart_data']['counts']->toArray(), 'date')),
        datasets: [{
            label: 'Transactions',
            data: @json(array_column($stats['chart_data']['counts']->toArray(), 'count')),
            backgroundColor: 'rgba(59, 130, 246, 0.5)',
            borderColor: 'rgb(59, 130, 246)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 1.5,
        plugins: {
            legend: {
                display: true,
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>
@endsection

@extends('layouts.admin')

@section('title', 'Card profit statistics')
@section('page-title', 'Card profit statistics')

@section('content')
@php
    $summary = $stats['summary'];
    $monthly = $stats['monthly'];
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Dollar Virtual Card profit</h2>
            <p class="text-sm text-gray-600 mt-1">FX markup from card requests, fund (top-up), and withdraw — based on wallet ledger</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.virtual-cards.index') }}" class="text-sm text-gray-600 hover:text-gray-900 font-medium">
                <i class="fas fa-arrow-left mr-1"></i> Card management
            </a>
            <a href="{{ route('admin.virtual-cards.users') }}" class="text-sm text-violet-700 hover:underline font-medium">
                <i class="fas fa-users mr-1"></i> Card users
            </a>
            <a href="{{ route('admin.virtual-cards.rate-tracker') }}" class="text-sm text-cyan-700 hover:underline font-medium">
                <i class="fas fa-chart-area mr-1"></i> FX rate tracker
            </a>
            <a href="{{ route('admin.virtual-cards.logs') }}" class="text-sm text-indigo-700 hover:underline font-medium">
                <i class="fas fa-list-alt mr-1"></i> Request logs
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">From</label>
                <input type="date" name="from" value="{{ $stats['from'] ?? request('from') }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">To</label>
                <input type="date" name="to" value="{{ $stats['to'] ?? request('to') }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <button type="submit" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 text-sm">Apply</button>
            <a href="{{ route('admin.virtual-cards.stats') }}" class="text-gray-600 hover:text-gray-900 text-sm py-2">Clear</a>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-emerald-50 to-green-50 rounded-lg border-2 border-emerald-200 p-5">
            <p class="text-xs text-gray-600 uppercase">Total FX profit</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">₦{{ number_format($summary['total_profit_ngn'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-2">Request + fund + withdraw markup</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Card setup profit</p>
            <p class="text-2xl font-bold text-indigo-700">₦{{ number_format($summary['request_profit_ngn'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-2">{{ number_format($summary['request_count']) }} fees · gross ₦{{ number_format($summary['request_gross_ngn'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Fund card profit</p>
            <p class="text-2xl font-bold text-blue-700">₦{{ number_format($summary['topup_profit_ngn'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-2">{{ number_format($summary['topup_count']) }} top-ups · gross ₦{{ number_format($summary['topup_gross_ngn'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Withdraw profit</p>
            <p class="text-2xl font-bold text-violet-700">₦{{ number_format($summary['withdraw_profit_ngn'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-2">{{ number_format($summary['withdraw_count']) }} withdraws · gross ₦{{ number_format($summary['withdraw_gross_ngn'], 2) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Active cards</p>
            <p class="text-2xl font-bold text-green-700">{{ number_format($summary['active_cards']) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Refunded request fees</p>
            <p class="text-2xl font-bold text-amber-700">{{ number_format($summary['refunded_request_fees']) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm text-sm text-gray-600">
            <p class="font-medium text-gray-900 mb-1">How profit is calculated</p>
            <p>Card setup, fund &amp; request: total USD charged × (sell rate − mid rate). Setup is ${{ number_format(app(\App\Services\Consumer\ConsumerVirtualCardService::class)->requestFeeUsd(), 2) }} today ($2.50 setup + $5.00 load unless you changed settings). Withdraw: USD amount × (mid rate − buy rate). Refunded fees are excluded.</p>
            <p class="mt-2 text-xs text-gray-500">Card merchant spend at stores is not in this ledger yet — only wallet movements tied to the card product.</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Profit over time (monthly)</h3>
        <div class="h-80">
            <canvas id="vcProfitChart"></canvas>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">Monthly breakdown</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Month</th>
                        <th class="px-4 py-3">Request</th>
                        <th class="px-4 py-3">Fund</th>
                        <th class="px-4 py-3">Withdraw</th>
                        <th class="px-4 py-3">Total profit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($monthly as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $row['label'] }}</td>
                            <td class="px-4 py-3">₦{{ number_format($row['request_profit'], 2) }}</td>
                            <td class="px-4 py-3">₦{{ number_format($row['topup_profit'], 2) }}</td>
                            <td class="px-4 py-3">₦{{ number_format($row['withdraw_profit'], 2) }}</td>
                            <td class="px-4 py-3 font-semibold text-emerald-700">₦{{ number_format($row['total_profit'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">No card profit data yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(() => {
    const monthly = @json($monthly);
    const ctx = document.getElementById('vcProfitChart');
    if (!ctx || !monthly.length) {
        return;
    }
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: monthly.map((row) => row.label),
            datasets: [
                {
                    label: 'Request fees',
                    data: monthly.map((row) => row.request_profit),
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.25,
                },
                {
                    label: 'Fund card',
                    data: monthly.map((row) => row.topup_profit),
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.25,
                },
                {
                    label: 'Withdraw',
                    data: monthly.map((row) => row.withdraw_profit),
                    borderColor: '#7c3aed',
                    backgroundColor: 'rgba(124, 58, 237, 0.1)',
                    tension: 0.25,
                },
                {
                    label: 'Total profit',
                    data: monthly.map((row) => row.total_profit),
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.08)',
                    borderWidth: 2,
                    tension: 0.25,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    ticks: {
                        callback: (value) => '₦' + Number(value).toLocaleString(),
                    },
                },
            },
        },
    });
})();
</script>
@endpush

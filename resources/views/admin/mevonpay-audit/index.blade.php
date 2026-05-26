@extends('layouts.admin')

@section('title', 'Mevon Pay audit')
@section('page-title', 'Mevon Pay audit')

@section('content')
<div class="space-y-6">
    <nav class="text-sm text-gray-500">
        <a href="{{ route('admin.audits.index') }}" class="text-indigo-600 hover:underline">Audits</a>
        <span class="mx-1">/</span>
        <span class="text-gray-700">Mevon Pay</span>
    </nav>
    <form method="get" class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
            <input type="date" name="from" value="{{ $from }}" class="border border-gray-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
            <input type="date" name="to" value="{{ $to }}" class="border border-gray-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Opening balance (optional)</label>
            <input type="number" step="0.01" name="opening_balance" value="{{ $openingBalance }}" placeholder="0" class="border border-gray-300 rounded-md px-3 py-2 text-sm w-40">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Direction</label>
            <select name="direction" class="border border-gray-300 rounded-md px-3 py-2 text-sm">
                <option value="">All</option>
                <option value="inbound" @selected($direction === 'inbound')>Inbound</option>
                <option value="outbound" @selected($direction === 'outbound')>Outbound</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Flow</label>
            <select name="flow_type" class="border border-gray-300 rounded-md px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach($flowTypes as $ft)
                    <option value="{{ $ft }}" @selected($flowType === $ft)>{{ str_replace('_', ' ', $ft) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-md text-sm font-medium">Apply</button>
        <a href="{{ route('admin.audits.mevonpay.export', request()->query()) }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm">Export CSV</a>
    </form>

    @php $r = $report; @endphp
    <div class="rounded-lg border p-4 {{ $r['within_tolerance'] ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200' }}">
        <p class="font-semibold text-gray-900">
            Reconciliation {{ $r['within_tolerance'] ? 'within tolerance' : 'variance detected' }}
            <span class="text-sm font-normal text-gray-600">({{ $r['entry_count'] }} ledger entries)</span>
        </p>
        @if(!$r['balance_ok'])
            <p class="text-sm text-red-700 mt-1">Live balance: {{ $r['balance_message'] }}</p>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Inbound gross</p>
            <p class="text-xl font-bold">₦{{ number_format($r['inbound_gross'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">Fees: ₦{{ number_format($r['inbound_fees'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Outbound gross</p>
            <p class="text-xl font-bold">₦{{ number_format($r['outbound_gross'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">API fees: ₦{{ number_format($r['outbound_fees'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Net Mevon impact</p>
            <p class="text-xl font-bold">₦{{ number_format($r['net_mevon_impact'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">Expected: ₦{{ number_format($r['expected_balance_from_ledger'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Live Mevon balance</p>
            <p class="text-xl font-bold">@if($r['live_naira_balance'] !== null)₦{{ number_format($r['live_naira_balance'], 2) }}@else—@endif</p>
            <p class="text-xs mt-1 {{ abs($r['variance_vs_live_balance'] ?? 0) > $r['tolerance'] ? 'text-amber-700' : 'text-gray-500' }}">
                Variance: @if($r['variance_vs_live_balance'] !== null)₦{{ number_format($r['variance_vs_live_balance'], 2) }}@else—@endif
            </p>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-gray-200 font-medium text-gray-900">Ledger entries</div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-3 py-2">When</th>
                    <th class="px-3 py-2">Dir</th>
                    <th class="px-3 py-2">Flow</th>
                    <th class="px-3 py-2">Gross</th>
                    <th class="px-3 py-2">Fees</th>
                    <th class="px-3 py-2">Net</th>
                    <th class="px-3 py-2">Reference</th>
                    <th class="px-3 py-2">Source</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($ledger as $entry)
                @php
                    $walletTxnUrl = $entry->adminWalletTransactionUrl();
                    $walletTxnLabel = $entry->adminWalletTransactionLabel();
                @endphp
                <tr>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $entry->occurred_at?->format('Y-m-d H:i') }}</td>
                    <td class="px-3 py-2">{{ $entry->direction }}</td>
                    <td class="px-3 py-2">{{ $entry->flowTypeLabel() }}</td>
                    <td class="px-3 py-2">₦{{ number_format((float) $entry->gross_amount, 2) }}</td>
                    <td class="px-3 py-2">@if($entry->mevon_inbound_fee)₦{{ $entry->mevon_inbound_fee }}@endif @if($entry->mevon_outbound_fee)₦{{ $entry->mevon_outbound_fee }}@endif</td>
                    <td class="px-3 py-2">₦{{ number_format((float) $entry->net_mevon_impact, 2) }}</td>
                    <td class="px-3 py-2 text-xs font-mono">{{ $entry->external_reference ?: $entry->payout_reference ?: '—' }}</td>
                    <td class="px-3 py-2 text-xs">
                        @if($walletTxnUrl)
                            <a href="{{ $walletTxnUrl }}" class="text-primary hover:underline font-medium">{{ $walletTxnLabel }}</a>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No ledger entries in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3">{{ $ledger->links() }}</div>
    </div>
</div>
@endsection

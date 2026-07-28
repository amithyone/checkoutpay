@extends('layouts.admin')

@section('title', 'Mevon Balance Monitor')
@section('page-title', 'Mevon Balance Monitor')

@section('content')
@php
    $s = $summary;
    $b = $baseline;
    $fmt = fn (?float $n): string => $n !== null ? '₦'.number_format($n, 2) : '—';
@endphp
<div class="space-y-6">
    <nav class="text-sm text-gray-500 flex flex-wrap items-center gap-x-1">
        <a href="{{ route('admin.audits.index') }}" class="text-indigo-600 hover:underline">Audits</a>
        <span>/</span>
        <a href="{{ route('admin.audits.mevonpay.index') }}" class="text-indigo-600 hover:underline">Mevon Pay</a>
        <span>/</span>
        <span class="text-gray-700">Balance Monitor</span>
    </nav>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if(!$b['active'])
        <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-indigo-900">Start monitoring from now</h2>
            <p class="mt-2 text-sm text-indigo-800 max-w-3xl">
                Capture the current live Mevon master balance as day-zero. All ledger entries <strong>before</strong> that moment are ignored —
                this monitor tracks income, fees, and payouts from deploy forward only. It does not reconcile historical incidents.
            </p>
            <form method="post" action="{{ route('admin.audits.mevonpay.monitor.baseline') }}" class="mt-4">
                @csrf
                <button type="submit" class="px-5 py-2.5 bg-primary text-white rounded-md text-sm font-semibold hover:opacity-90">
                    Start monitoring now
                </button>
            </form>
        </div>
    @else
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-gray-900">Monitoring baseline</p>
                <p class="text-sm text-gray-600 mt-1">
                    Started {{ $b['baseline_at'] ? \Illuminate\Support\Carbon::parse($b['baseline_at'])->format('Y-m-d H:i:s T') : '—' }}
                    @if($b['started_by_admin_name'])
                        · by {{ $b['started_by_admin_name'] }}
                    @endif
                </p>
                <p class="text-sm text-gray-600 mt-1">
                    Opening balance: {{ $fmt($b['opening_balance']) }}
                    @if($b['opening_ledger'] !== null)
                        · API ledger: {{ $fmt($b['opening_ledger']) }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="post" action="{{ route('admin.audits.mevonpay.monitor.check') }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium bg-white hover:bg-gray-50">
                        Check balance now
                    </button>
                </form>
                @if($isSuperAdmin)
                    <form method="post" action="{{ route('admin.audits.mevonpay.monitor.baseline.reset') }}"
                          onsubmit="return confirm('Reset baseline? This starts fresh from the current live balance and excludes prior ledger totals from this monitor.');">
                        @csrf
                        <input type="hidden" name="confirm_reset" value="1">
                        <button type="submit" class="px-4 py-2 border border-red-300 text-red-700 rounded-md text-sm font-medium bg-white hover:bg-red-50">
                            Reset baseline
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    @if($b['active'])
        <div class="rounded-lg border p-4 {{ $s['within_tolerance'] ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
            <p class="font-semibold text-gray-900">
                Live status: {{ $s['within_tolerance'] ? 'Within tolerance' : 'Variance detected' }}
            </p>
            @if(!$s['balance_ok'])
                <p class="text-sm text-red-700 mt-1">{{ $s['balance_message'] }}</p>
            @else
                <p class="text-sm text-gray-600 mt-1">
                    Expected {{ $fmt($s['expected_balance']) }}
                    · Live {{ $fmt($s['live_naira_balance']) }}
                    · Variance {{ $fmt($s['variance_amount']) }}
                    · Tolerance ±{{ $fmt($s['tolerance']) }}
                </p>
            @endif
            @if($s['last_checked_at'])
                <p class="text-xs text-gray-500 mt-1">Last alert check: {{ \Illuminate\Support\Carbon::parse($s['last_checked_at'])->format('Y-m-d H:i:s') }}</p>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
                <p class="text-xs text-gray-500 uppercase">Inbound gross</p>
                <p class="text-xl font-bold">{{ $fmt($s['inbound_gross']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Fees: {{ $fmt($s['inbound_fees']) }}</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
                <p class="text-xs text-gray-500 uppercase">Outbound gross</p>
                <p class="text-xl font-bold">{{ $fmt($s['outbound_gross']) }}</p>
                <p class="text-xs text-gray-500 mt-1">API fees: {{ $fmt($s['outbound_fees']) }}</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
                <p class="text-xs text-gray-500 uppercase">Net Mevon impact</p>
                <p class="text-xl font-bold">{{ $fmt($s['net_mevon_impact']) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ number_format($s['entry_count']) }} entries since baseline</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
                <p class="text-xs text-gray-500 uppercase">Discrepancy alerts</p>
                <p class="text-xl font-bold">{{ number_format($s['alert_count']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Recorded when variance exceeds tolerance</p>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-gray-200 font-medium text-gray-900">Discrepancy history</div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-3 py-2">When</th>
                        <th class="px-3 py-2">Expected</th>
                        <th class="px-3 py-2">Live</th>
                        <th class="px-3 py-2">Variance</th>
                        <th class="px-3 py-2">Trigger</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($alerts as $alert)
                    <tr>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $alert->checked_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="px-3 py-2">{{ $fmt((float) $alert->expected_balance) }}</td>
                        <td class="px-3 py-2">{{ $fmt((float) $alert->live_balance) }}</td>
                        <td class="px-3 py-2 font-medium text-red-700">{{ $fmt((float) $alert->variance_amount) }}</td>
                        <td class="px-3 py-2">{{ $alert->triggerLabel() }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No discrepancy alerts recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-gray-200 font-medium text-gray-900">Transaction ledger (since baseline)</div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-3 py-2">When</th>
                        <th class="px-3 py-2">Dir</th>
                        <th class="px-3 py-2">Flow</th>
                        <th class="px-3 py-2">Gross</th>
                        <th class="px-3 py-2">Fees</th>
                        <th class="px-3 py-2">Net</th>
                        <th class="px-3 py-2">Running balance</th>
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
                        <td class="px-3 py-2">{{ $fmt((float) $entry->gross_amount) }}</td>
                        <td class="px-3 py-2">
                            @if($entry->mevon_inbound_fee)₦{{ $entry->mevon_inbound_fee }}@endif
                            @if($entry->mevon_outbound_fee)₦{{ $entry->mevon_outbound_fee }}@endif
                        </td>
                        <td class="px-3 py-2">{{ $fmt((float) $entry->net_mevon_impact) }}</td>
                        <td class="px-3 py-2 font-medium">{{ $fmt($entry->running_expected_balance !== null ? (float) $entry->running_expected_balance : null) }}</td>
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
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">No ledger entries since baseline.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-4 py-3">{{ $ledger->links() }}</div>
        </div>
    @endif

    <p class="text-xs text-gray-500">
        For ad-hoc date-range audits and CSV export, use
        <a href="{{ route('admin.audits.mevonpay.index') }}" class="text-indigo-600 hover:underline">Mevon Pay audit</a>.
        Scheduled checks run every 15 minutes via <code class="text-xs">mevon:check-balance</code>.
    </p>
</div>
@endsection

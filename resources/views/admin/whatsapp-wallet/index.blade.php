@extends('layouts.admin')

@section('title', 'WhatsApp wallet')
@section('page-title', 'WhatsApp wallet')

@section('content')
<div class="space-y-6">
    @include('admin.whatsapp-wallet.partials.nav', [
        'failedCount' => $failedPayoutCount ?? 0,
        'pendingCount' => $pendingPayoutCount ?? 0,
    ])

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="{{ route('admin.whatsapp-wallet.wallets.index') }}" class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm hover:border-green-300 transition">
            <p class="text-sm text-gray-500">Wallet users</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($walletTotal) }}</p>
            <p class="text-xs text-green-700 mt-2">{{ number_format($walletsWithPin) }} with PIN · Manage →</p>
        </a>
        <a href="{{ route('admin.whatsapp-wallet.transactions.index') }}" class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm hover:border-green-300 transition">
            <p class="text-sm text-gray-500">Transactions (all time)</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($txTotal) }}</p>
            <p class="text-xs text-gray-500 mt-2">7d: {{ number_format($txLast7d) }} · 30d: {{ number_format($txLast30d) }}</p>
        </a>
        <a href="{{ route('admin.whatsapp-wallet.transactions.p2p') }}" class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm hover:border-green-300 transition">
            <p class="text-sm text-gray-500">P2P (last 7 days)</p>
            <p class="text-2xl font-bold text-primary">{{ number_format($p2pCount7d ?? 0) }}</p>
            <p class="text-xs text-green-700 mt-2">Wallet-to-wallet sends →</p>
        </a>
        <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
            <p class="text-sm text-gray-500">Bank payouts needing attention</p>
            <div class="flex gap-4 mt-2">
                <a href="{{ route('admin.whatsapp-wallet.transactions.pending') }}" class="text-amber-700 font-bold text-xl hover:underline">
                    {{ number_format($pendingPayoutCount ?? 0) }} <span class="text-sm font-normal">pending</span>
                </a>
                <a href="{{ route('admin.whatsapp-wallet.transactions.failed') }}" class="text-red-700 font-bold text-xl hover:underline">
                    {{ number_format($failedPayoutCount ?? 0) }} <span class="text-sm font-normal">failed</span>
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Wallets by country</h3>
            @if($walletsByCountry === [])
                <p class="text-sm text-gray-500">No wallets yet.</p>
            @else
                <ul class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                    @foreach($walletsByCountry as $label => $cnt)
                        <li class="py-2 flex justify-between text-sm">
                            <span>{{ $label }}</span>
                            <span class="font-medium">{{ number_format($cnt) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Transactions by type</h3>
            @if($txByType === [])
                <p class="text-sm text-gray-500">No transactions yet.</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach($txByType as $type => $cnt)
                        <a href="{{ route('admin.whatsapp-wallet.transactions.index', ['type' => $type === \App\Models\WhatsappWalletTransaction::TYPE_P2P_DEBIT || $type === \App\Models\WhatsappWalletTransaction::TYPE_P2P_CREDIT ? 'p2p' : $type]) }}"
                           class="inline-flex items-center bg-gray-100 hover:bg-green-50 rounded-full px-3 py-1 text-sm">
                            <span class="font-mono text-gray-700">{{ str_replace('_', ' ', $type) }}</span>
                            <span class="ml-2 font-semibold">{{ number_format($cnt) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Recent activity</h3>
                <p class="text-sm text-gray-500">Latest 40 ledger rows</p>
            </div>
            <a href="{{ route('admin.whatsapp-wallet.transactions.index') }}" class="text-sm text-primary hover:underline">View all</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-2">ID</th>
                        <th class="px-4 py-2">When</th>
                        <th class="px-4 py-2">Type</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                        <th class="px-4 py-2">Wallet</th>
                        <th class="px-4 py-2">Counterparty</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($recentTx as $t)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono text-xs">#{{ $t->id }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $t->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-2 text-xs">{{ str_replace('_', ' ', $t->type) }}</td>
                            <td class="px-4 py-2 text-right font-medium">₦{{ number_format((float) $t->amount, 2) }}</td>
                            <td class="px-4 py-2">
                                @if($t->wallet)
                                    <a href="{{ route('admin.whatsapp-wallet.wallets.show', $t->wallet) }}" class="font-mono text-xs text-primary hover:underline">{{ $t->wallet->phone_e164 }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $t->counterparty_phone_e164 ?? $t->counterparty_account_number ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('admin.whatsapp-wallet.transactions.show', $t) }}" class="text-primary hover:underline text-xs">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No transactions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Enabled regions</h3>
        <p class="text-xs text-gray-500 mb-3">Evolution instances merged from config + admin settings.</p>
        @if(($regions ?? []) === [])
            <p class="text-sm text-gray-500">No instances configured. <a href="{{ route('admin.whatsapp-wallet.settings') }}" class="text-primary underline">Open settings</a></p>
        @else
            <ul class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                @foreach($regions as $instanceName => $row)
                    @if(is_array($row))
                        <li class="border border-gray-100 rounded-lg p-3">
                            <span class="font-medium">{{ $instanceName }}</span>
                            <span class="text-gray-600"> · {{ $row['label'] ?? $row['country'] ?? '' }} ({{ $row['currency'] ?? '' }})</span>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection

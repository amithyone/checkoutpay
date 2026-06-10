@extends('layouts.admin')

@section('title', 'Card Users')
@section('page-title', 'Card Users')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Card Users</h2>
            <p class="text-sm text-gray-600 mt-1">Users with active Dollar Virtual Cards — view balances and transaction history</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.virtual-cards.index') }}" class="text-sm text-gray-600 hover:text-gray-900 font-medium py-2">
                <i class="fas fa-arrow-left mr-1"></i> Card requests
            </a>
            <a href="{{ route('admin.virtual-cards.rate-tracker') }}" class="text-sm text-cyan-700 hover:underline font-medium py-2">
                <i class="fas fa-chart-area mr-1"></i> FX rate tracker
            </a>
            <a href="{{ route('admin.virtual-cards.stats') }}" class="text-sm text-emerald-700 hover:underline font-medium py-2">
                <i class="fas fa-chart-line mr-1"></i> Profit statistics
            </a>
            <a href="{{ route('admin.virtual-cards.logs') }}" class="text-sm text-indigo-700 hover:underline font-medium py-2">
                <i class="fas fa-list-alt mr-1"></i> Request &amp; webhook logs
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-500 mb-1">Search card users</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Phone, card name, provider ID"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <button type="submit" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 text-sm">Filter</button>
            <a href="{{ route('admin.virtual-cards.users') }}" class="text-gray-600 hover:text-gray-900 text-sm py-2">Clear</a>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User / Wallet</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Card name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ending 4</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Card Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wallet Bal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Card Bal (USD)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Activated</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($cards as $card)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm">
                            <div class="font-medium text-gray-900">{{ $card->wallet?->displayName() ?? '—' }}</div>
                            <div class="text-xs text-gray-500">{{ $card->wallet?->phone_e164 ?? '—' }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $card->card_name ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm font-mono text-gray-600">
                            @php
                                $lastFour = '—';
                                $stored = is_array($card->card_details_payload) ? $card->card_details_payload : null;
                                if ($stored) {
                                    $lastFour = trim((string) ($stored['last_four'] ?? $stored['last4'] ?? '—'));
                                }
                            @endphp
                            {{ $lastFour }}
                        </td>
                        <td class="px-6 py-4">
                            @if($card->is_frozen)
                                <span class="bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded font-semibold">Frozen</span>
                            @else
                                <span class="bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded font-semibold">Active</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            ₦{{ number_format((float) ($card->wallet?->balance ?? 0), 2) }}
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">
                            ${{ number_format($card->card_balance_usd ?? 0, 2) }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $card->activated_at ? $card->activated_at->format('M d, Y H:i') : '—' }}
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.virtual-cards.show', $card) }}" class="text-primary hover:underline text-sm font-semibold">Open Account</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500">No active card users found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($cards->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $cards->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

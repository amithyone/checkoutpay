@extends('layouts.admin')

@section('title', $pageTitle ?? 'WhatsApp transactions')
@section('page-title', $pageTitle ?? 'WhatsApp transactions')

@section('content')
@php
    $bucketBadge = function (string $bucket): string {
        return match ($bucket) {
            'failed' => 'bg-red-100 text-red-800',
            'pending' => 'bg-amber-100 text-amber-800',
            'successful' => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-700',
        };
    };
    $listRoute = match ($viewMode ?? 'index') {
        'failed' => route('admin.whatsapp-wallet.transactions.failed'),
        'pending' => route('admin.whatsapp-wallet.transactions.pending'),
        default => route('admin.whatsapp-wallet.transactions.index'),
    };
@endphp
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ $pageTitle }}</h3>
            <p class="text-sm text-gray-600 mt-1">{{ $pageSubtitle }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.whatsapp-wallet.transactions.failed') }}"
               class="bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700 text-sm inline-flex items-center">
                <i class="fas fa-times-circle mr-2"></i> Failed
                @if(($failedCount ?? 0) > 0)
                    <span class="ml-2 bg-white text-red-600 rounded-full px-2 py-0.5 text-xs font-bold">{{ $failedCount }}</span>
                @endif
            </a>
            <a href="{{ route('admin.whatsapp-wallet.transactions.pending') }}"
               class="bg-amber-600 text-white px-3 py-2 rounded-lg hover:bg-amber-700 text-sm inline-flex items-center">
                <i class="fas fa-clock mr-2"></i> Pending
                @if(($pendingCount ?? 0) > 0)
                    <span class="ml-2 bg-white text-amber-700 rounded-full px-2 py-0.5 text-xs font-bold">{{ $pendingCount }}</span>
                @endif
            </a>
            <a href="{{ route('admin.whatsapp-wallet.transactions.index') }}"
               class="bg-gray-100 text-gray-800 px-3 py-2 rounded-lg hover:bg-gray-200 text-sm">
                All
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" action="{{ $listRoute }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Reference, phone, account, ID…"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Payout status</label>
                <select name="payout_status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="all" {{ request('payout_status', ($viewMode === 'failed' ? 'failed' : ($viewMode === 'pending' ? 'pending' : 'all'))) === 'all' ? 'selected' : '' }}>All</option>
                    <option value="failed" {{ request('payout_status') === 'failed' ? 'selected' : '' }}>Failed</option>
                    <option value="pending" {{ request('payout_status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="successful" {{ request('payout_status') === 'successful' ? 'selected' : '' }}>Successful</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Type</label>
                <select name="type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach($typeOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('type', 'bank_transfer_out') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm hover:opacity-90">Filter</button>
                <a href="{{ $listRoute }}" class="text-sm text-gray-600 hover:text-gray-900 py-2">Reset</a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">ID</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Date</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Phone</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Type</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600">Amount</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Payout</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Reference</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($transactions as $txn)
                        @php $bucket = $txn->payoutBucketLabel(); @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-900">#{{ $txn->id }}</td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $txn->created_at?->format('M j, Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ $txn->wallet?->phone_e164 ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ str_replace('_', ' ', $txn->type) }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900">₦{{ number_format((float) $txn->amount, 2) }}</td>
                            <td class="px-4 py-3">
                                @if($txn->type === \App\Models\WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT)
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $bucketBadge($bucket) }}">{{ ucfirst($bucket) }}</span>
                                    @if($txn->isReversed())
                                        <span class="text-xs text-gray-500 block">Reversed</span>
                                    @endif
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 font-mono text-xs max-w-[10rem] truncate" title="{{ $txn->external_reference }}">
                                {{ $txn->external_reference ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.whatsapp-wallet.transactions.show', $txn) }}" class="text-primary hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">No transactions match your filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($transactions->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">{{ $transactions->links() }}</div>
        @endif
    </div>
</div>
@endsection

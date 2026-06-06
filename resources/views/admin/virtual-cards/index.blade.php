@extends('layouts.admin')

@section('title', 'Card Management')
@section('page-title', 'Card Management')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Card Management</h2>
            <p class="text-sm text-gray-600 mt-1">Dollar Virtual Card requests from CheckoutNow — review, activate, retry, or refund fees</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.virtual-cards.logs') }}" class="text-sm text-indigo-700 hover:underline font-medium">
                <i class="fas fa-list-alt mr-1"></i> Request &amp; webhook logs
            </a>
            <a href="{{ route('admin.settings.index') }}#vtu-virtual-card" class="text-sm text-primary hover:underline">
                <i class="fas fa-cog mr-1"></i> Card settings (enable &amp; fee)
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Pending</p>
            <p class="text-2xl font-bold text-yellow-700">{{ number_format($stats['pending']) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-violet-200 p-4 shadow-sm bg-violet-50/40">
            <p class="text-xs text-gray-500 uppercase">Preparing</p>
            <p class="text-2xl font-bold text-violet-700">{{ number_format($stats['preparing'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-1">Awaiting webhook</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Submitted</p>
            <p class="text-2xl font-bold text-blue-700">{{ number_format($stats['submitted']) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Active</p>
            <p class="text-2xl font-bold text-green-700">{{ number_format($stats['active']) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Failed</p>
            <p class="text-2xl font-bold text-red-700">{{ number_format($stats['failed']) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-indigo-200 p-4 shadow-sm bg-indigo-50/50">
            <p class="text-xs text-gray-500 uppercase">Fees collected</p>
            <p class="text-xl font-bold text-gray-900">₦{{ number_format($stats['total_fees_ngn'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">Preparing + submitted + active</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Status</label>
                <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                    <option value="preparing" @selected(request('status') === 'preparing')>Preparing</option>
                    <option value="submitted" @selected(request('status') === 'submitted')>Submitted</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="failed" @selected(request('status') === 'failed')>Failed</option>
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-500 mb-1">Search</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Phone, reference, card name, provider ID"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <button type="submit" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 text-sm">Filter</button>
            <a href="{{ route('admin.virtual-cards.index') }}" class="text-gray-600 hover:text-gray-900 text-sm py-2">Clear</a>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wallet</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Card name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fee</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provider ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($cards as $card)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-900">#{{ $card->id }}</td>
                        <td class="px-6 py-4 text-sm">
                            <div class="font-medium text-gray-900">{{ $card->wallet?->displayName() ?? '—' }}</div>
                            <div class="text-xs text-gray-500">{{ $card->wallet?->phone_e164 ?? '—' }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $card->card_name ?? '—' }}</td>
                        <td class="px-6 py-4">@include('admin.virtual-cards._status-badge', ['status' => $card->status])</td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            ${{ number_format($card->fee_usd, 2) }}
                            <span class="text-gray-500 text-xs block">₦{{ number_format($card->fee_ngn, 2) }}</span>
                        </td>
                        <td class="px-6 py-4 text-xs font-mono text-gray-600">{{ $card->external_reference ?? '—' }}</td>
                        <td class="px-6 py-4 text-xs font-mono text-gray-600">{{ $card->card_external_id ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $card->created_at->format('M d, Y H:i') }}</td>
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.virtual-cards.show', $card) }}" class="text-primary hover:underline text-sm">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-sm text-gray-500">No card requests found</td>
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

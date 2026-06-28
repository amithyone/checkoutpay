@extends('layouts.admin')

@section('title', $pageTitle ?? 'Money requests')
@section('page-title', $pageTitle ?? 'Money requests')

@section('content')
<div class="space-y-6">
    @include('admin.whatsapp-wallet.partials.nav')

    <div>
        <h3 class="text-lg font-semibold text-gray-900">{{ $pageTitle }}</h3>
        <p class="text-sm text-gray-600 mt-1">{{ $pageSubtitle }}</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" action="{{ route('admin.whatsapp-wallet.money-requests.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Phone (requester or payer)</label>
                <input type="text" name="phone" value="{{ request('phone') }}"
                    placeholder="23480…"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach(['all' => 'All', 'pending' => 'Pending', 'accepted' => 'Accepted', 'declined' => 'Declined', 'cancelled' => 'Cancelled', 'expired' => 'Expired'] as $value => $label)
                        <option value="{{ $value }}" @selected(($statusFilter ?? 'all') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="bg-green-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-800">Filter</button>
                <a href="{{ route('admin.whatsapp-wallet.money-requests.index') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">ID</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Requester</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Payer</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Amount</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Channel</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Created</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Expires</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($requests as $request)
                        @php
                            $statusClass = match ($request->status) {
                                'pending' => 'bg-amber-100 text-amber-800',
                                'accepted' => 'bg-green-100 text-green-800',
                                'declined', 'cancelled' => 'bg-gray-100 text-gray-700',
                                'expired' => 'bg-red-100 text-red-800',
                                default => 'bg-gray-100 text-gray-700',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ Str::limit($request->public_id, 8, '') }}</td>
                            <td class="px-4 py-3">
                                <div>{{ $request->requesterWallet?->displayName() ?? $request->requester_phone_e164 }}</div>
                                <div class="text-xs text-gray-500">{{ $request->requester_phone_e164 }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ $request->payerWallet?->displayName() ?? $request->payer_phone_e164 }}</div>
                                <div class="text-xs text-gray-500">{{ $request->payer_phone_e164 }}</div>
                            </td>
                            <td class="px-4 py-3 font-medium">₦{{ number_format((float) $request->amount, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusClass }}">{{ $request->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $request->channel }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $request->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $request->expires_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">No money requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">{{ $requests->links() }}</div>
        @endif
    </div>
</div>
@endsection

@extends('layouts.admin')

@section('title', $pageTitle ?? 'Save Together')
@section('page-title', $pageTitle ?? 'Save Together')

@section('content')
<div class="space-y-6">
    @include('admin.whatsapp-wallet.partials.nav')

    <div>
        <h3 class="text-lg font-semibold text-gray-900">{{ $pageTitle }}</h3>
        <p class="text-sm text-gray-600 mt-1">{{ $pageSubtitle }}</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" action="{{ route('admin.whatsapp-wallet.save-together.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Member phone</label>
                <input type="text" name="phone" value="{{ request('phone') }}"
                    placeholder="23480…"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach(['all' => 'All', 'collecting' => 'Collecting', 'unlocked' => 'Unlocked', 'closed' => 'Closed'] as $value => $label)
                        <option value="{{ $value }}" @selected(($statusFilter ?? 'all') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="bg-green-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-800">Filter</button>
                <a href="{{ route('admin.whatsapp-wallet.save-together.index') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">ID</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Title</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Creator</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Target</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Escrow</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Members</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Mode</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pots as $pot)
                        @php
                            $statusClass = match ($pot->status) {
                                'collecting' => 'bg-amber-100 text-amber-800',
                                'unlocked' => 'bg-green-100 text-green-800',
                                'closed' => 'bg-gray-100 text-gray-700',
                                default => 'bg-gray-100 text-gray-700',
                            };
                            $completed = $pot->members->where('status', 'completed_share')->count();
                            $memberTotal = $pot->members->count();
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ Str::limit($pot->public_id, 8, '') }}</td>
                            <td class="px-4 py-3 font-medium">{{ $pot->title }}</td>
                            <td class="px-4 py-3">
                                <div>{{ $pot->creatorWallet?->displayName() ?? '—' }}</div>
                                <div class="text-xs text-gray-500">{{ $pot->creatorWallet?->phone_e164 }}</div>
                            </td>
                            <td class="px-4 py-3">₦{{ number_format((float) $pot->target_amount, 2) }}</td>
                            <td class="px-4 py-3">₦{{ number_format((float) $pot->total_contributed, 2) }}</td>
                            <td class="px-4 py-3">{{ $completed }}/{{ $memberTotal }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ str_replace('_', ' ', $pot->completion_mode) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusClass }}">{{ $pot->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $pot->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">No Save Together pots found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($pots->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">{{ $pots->links() }}</div>
        @endif
    </div>
</div>
@endsection

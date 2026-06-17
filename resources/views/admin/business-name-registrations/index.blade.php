@extends('layouts.admin')

@section('title', 'Business name registrations')
@section('page-title', 'Business name registrations')

@section('content')
@php
    $statusBadge = fn (string $status): string => match ($status) {
        \App\Models\BusinessNameRegistration::STATUS_APPROVED => 'bg-green-100 text-green-800',
        \App\Models\BusinessNameRegistration::STATUS_REJECTED => 'bg-red-100 text-red-800',
        \App\Models\BusinessNameRegistration::STATUS_UNDER_REVIEW => 'bg-indigo-100 text-indigo-800',
        \App\Models\BusinessNameRegistration::STATUS_PROCESSING => 'bg-blue-100 text-blue-800',
        \App\Models\BusinessNameRegistration::STATUS_PAID => 'bg-amber-100 text-amber-800',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp
<div class="space-y-6">
    @include('admin.whatsapp-wallet.partials.nav')

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-briefcase text-green-600 mr-2"></i> Business name registration queue
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    Review mobile app applications, update progress, and issue business receive accounts on approval.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                @if($pendingCount > 0)
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-amber-100 text-amber-900 font-medium">
                        {{ $pendingCount }} awaiting review
                    </span>
                @endif
            </div>
        </div>

        <form method="GET" action="{{ route('admin.business-name-registrations.index') }}" class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @if(request('wallet_id'))
                <input type="hidden" name="wallet_id" value="{{ request('wallet_id') }}">
            @endif
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Reference, business name, phone, email…"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending review</option>
                    <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="processing" {{ $status === 'processing' ? 'selected' : '' }}>Processing</option>
                    <option value="under_review" {{ $status === 'under_review' ? 'selected' : '' }}>Under review</option>
                    <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm hover:bg-gray-800">Filter</button>
                <a href="{{ route('admin.business-name-registrations.index') }}" class="px-4 py-2 bg-gray-100 text-gray-800 rounded-lg text-sm hover:bg-gray-200">Reset</a>
            </div>
        </form>
    </div>

    @if($registrations->isEmpty())
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">
            No applications found for this filter.
        </div>
    @else
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-gray-600 font-medium">Reference</th>
                            <th class="px-4 py-3 text-left text-gray-600 font-medium">Business name</th>
                            <th class="px-4 py-3 text-left text-gray-600 font-medium">Wallet</th>
                            <th class="px-4 py-3 text-left text-gray-600 font-medium">Status</th>
                            <th class="px-4 py-3 text-left text-gray-600 font-medium">Progress</th>
                            <th class="px-4 py-3 text-left text-gray-600 font-medium">Fee</th>
                            <th class="px-4 py-3 text-left text-gray-600 font-medium">Submitted</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($registrations as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-mono text-xs">{{ $row->reference }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ $row->proposed_name }}</div>
                                    @if($row->alternate_name)
                                        <div class="text-xs text-gray-500">Alt: {{ $row->alternate_name }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($row->wallet)
                                        <a href="{{ route('admin.whatsapp-wallet.wallets.show', $row->wallet) }}" class="font-mono text-primary hover:underline">
                                            {{ $row->wallet->phone_e164 }}
                                        </a>
                                        <div class="text-xs text-gray-500">{{ $row->wallet->displayName() ?? '—' }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $statusBadge($row->status) }}">
                                        {{ $row->statusDisplayLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="w-28">
                                        <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                            <span>{{ (int) $row->progress_percent }}%</span>
                                        </div>
                                        <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-green-600 rounded-full" style="width: {{ min(100, max(0, (int) $row->progress_percent)) }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    ₦{{ number_format((float) $row->fee_amount, 2) }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-600">
                                    {{ $row->submitted_at?->format('M j, Y H:i') ?? $row->created_at?->format('M j, Y H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.business-name-registrations.show', $row) }}" class="text-primary hover:underline font-medium">Review</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($registrations->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $registrations->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection

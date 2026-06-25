@extends('layouts.admin')

@section('title', 'Business account applications')
@section('page-title', 'Business account applications')

@section('content')
@php
    $statusBadge = fn (string $status): string => match ($status) {
        \App\Models\BusinessAccountApplication::STATUS_ACTIVE => 'bg-green-100 text-green-800',
        \App\Models\BusinessAccountApplication::STATUS_REJECTED => 'bg-red-100 text-red-800',
        \App\Models\BusinessAccountApplication::STATUS_UNDER_REVIEW => 'bg-indigo-100 text-indigo-800',
        \App\Models\BusinessAccountApplication::STATUS_AWAITING_PASSWORD => 'bg-blue-100 text-blue-800',
        \App\Models\BusinessAccountApplication::STATUS_SUBMITTED => 'bg-amber-100 text-amber-800',
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
                    <i class="fas fa-store text-primary mr-2"></i> Checkout business account queue
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    Review CheckoutNow applications, provision merchant accounts, and link wallets.
                </p>
            </div>
            @if($pendingCount > 0)
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-amber-100 text-amber-900 font-medium text-sm">
                    {{ $pendingCount }} awaiting review
                </span>
            @endif
        </div>

        <form method="GET" action="{{ route('admin.business-account-applications.index') }}" class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @if(request('wallet_id'))
                <input type="hidden" name="wallet_id" value="{{ request('wallet_id') }}">
            @endif
            <div class="sm:col-span-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search reference, name, email, phone…"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending review</option>
                    <option value="submitted" {{ $status === 'submitted' ? 'selected' : '' }}>Submitted</option>
                    <option value="under_review" {{ $status === 'under_review' ? 'selected' : '' }}>Under review</option>
                    <option value="awaiting_password" {{ $status === 'awaiting_password' ? 'selected' : '' }}>Awaiting password</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm">Filter</button>
                <a href="{{ route('admin.business-account-applications.index') }}" class="px-4 py-2 bg-gray-100 text-gray-800 rounded-lg text-sm">Reset</a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Reference</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Business</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Plan</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Wallet</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-600"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($applications as $row)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">{{ $row->reference }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $row->business_name }}</div>
                            <div class="text-xs text-gray-500">{{ $row->email }}</div>
                        </td>
                        <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $row->account_plan) }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $row->wallet?->phone_e164 ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium {{ $statusBadge($row->status) }}">
                                {{ $row->statusDisplayLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.business-account-applications.show', $row) }}" class="text-primary hover:underline font-medium">Review</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-500">No applications found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $applications->links() }}
</div>
@endsection

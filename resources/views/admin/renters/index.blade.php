@extends('layouts.admin')

@section('title', 'Rental users')
@section('page-title', 'Rental users')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="text-sm text-gray-600">All registered renters (rentals platform)</div>
                <div class="text-xs text-gray-500">Search by name, email, or phone. Open a user to edit profile and wallet balance.</div>
            </div>

            <form method="GET" action="{{ route('admin.renters.index') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <input type="search" name="q" value="{{ $search }}" placeholder="Search…"
                       class="border border-gray-300 rounded-lg text-sm px-3 py-2 min-w-[200px] bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" />
                <select name="active" class="border border-gray-300 rounded-lg text-sm px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                    <option value="" {{ $activeFilter === null || $activeFilter === '' ? 'selected' : '' }}>All accounts</option>
                    <option value="1" {{ (string) $activeFilter === '1' ? 'selected' : '' }}>Active only</option>
                    <option value="0" {{ (string) $activeFilter === '0' ? 'selected' : '' }}>Disabled only</option>
                </select>
                <button type="submit" class="px-3 py-2 rounded-lg bg-gray-900 text-white text-sm">Apply</button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">ID</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Name</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Email</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Phone</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600">Wallet</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">KYC</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($renters as $renter)
                        @php
                            $kycStatus = $renter->kyc_id_status ?: 'pending';
                            $kycBadge = $kycStatus === 'approved' ? 'bg-green-100 text-green-800' : ($kycStatus === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800');
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-gray-600">#{{ $renter->id }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                {{ $renter->name }}
                                @if($renter->balance_audit_exempt)
                                    <span class="ml-1 text-[10px] uppercase tracking-wide text-amber-700">test</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700 break-all">{{ $renter->email }}</td>
                            <td class="px-4 py-3 text-gray-700 font-mono">{{ $renter->phone ?: '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono font-medium text-gray-900">₦{{ number_format((float) $renter->wallet_balance, 2) }}</td>
                            <td class="px-4 py-3">
                                @if($renter->is_active)
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Active</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">Disabled</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $kycBadge }}">{{ ucfirst($kycStatus) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.renters.show', $renter) }}" class="text-primary hover:underline font-medium">Edit</a>
                                <span class="text-gray-300 mx-1">·</span>
                                <a href="{{ route('admin.renters-kyc.index', ['renter' => $renter->id]) }}" class="text-gray-600 hover:underline">KYC</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">No renters match your filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($renters->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $renters->links() }}</div>
        @endif
    </div>
</div>
@endsection

@extends('layouts.admin')

@section('title', 'Rental users')
@section('page-title', 'Rental users')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="text-sm text-gray-600">All registered renters (rentals platform)</div>
                <div class="text-xs text-gray-500">Search by name, email, or phone. Use KYC page to review ID documents.</div>
            </div>

            <form method="GET" action="{{ route('admin.renters.index') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <input type="search" name="q" value="{{ $search }}" placeholder="Search…" class="border border-gray-300 rounded-md text-sm px-3 py-2 min-w-[200px]" />
                <select name="active" class="border border-gray-300 rounded-md text-sm px-3 py-2">
                    <option value="" {{ $activeFilter === null || $activeFilter === '' ? 'selected' : '' }}>All accounts</option>
                    <option value="1" {{ (string) $activeFilter === '1' ? 'selected' : '' }}>Active only</option>
                    <option value="0" {{ (string) $activeFilter === '0' ? 'selected' : '' }}>Disabled only</option>
                </select>
                <button type="submit" class="px-3 py-2 rounded-md bg-gray-900 text-white text-sm">Apply</button>
            </form>
        </div>
    </div>

    @if($renters->isEmpty())
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">
            No renters match your filters.
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($renters as $renter)
                @php
                    $kycStatus = $renter->kyc_id_status ?: 'pending';
                    $kycBadge = $kycStatus === 'approved' ? 'bg-green-100 text-green-800' : ($kycStatus === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800');
                @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-900 truncate">{{ $renter->name }}</div>
                            <div class="text-xs text-gray-500 truncate">ID #{{ $renter->id }}</div>
                        </div>
                        <div class="flex flex-col items-end gap-1 shrink-0">
                            @if($renter->is_active)
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Active</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">Disabled</span>
                            @endif
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $kycBadge }}">ID: {{ ucfirst($kycStatus) }}</span>
                        </div>
                    </div>
                    <div class="px-4 py-3 space-y-2 text-sm flex-1">
                        <div class="text-gray-700 break-all"><span class="text-gray-500 text-xs font-semibold uppercase">Email</span><br>{{ $renter->email }}</div>
                        <div class="text-gray-700"><span class="text-gray-500 text-xs font-semibold uppercase">Phone</span><br>{{ $renter->phone ?: '—' }}</div>
                        <div class="text-gray-700 text-xs">
                            <span class="text-gray-500 font-semibold uppercase">Bank</span><br>
                            {{ $renter->verified_bank_name ?? '—' }} · {{ $renter->verified_account_number ?? '—' }}
                        </div>
                        <div class="text-xs text-gray-500">
                            Updated {{ $renter->updated_at?->diffForHumans() ?? '—' }}
                        </div>
                    </div>
                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-100">
                        <a href="{{ route('admin.renters-kyc.index', ['renter' => $renter->id]) }}" class="inline-flex px-3 py-2 rounded-md bg-primary text-white text-xs font-semibold">KYC &amp; documents</a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $renters->links() }}
        </div>
    @endif
</div>
@endsection

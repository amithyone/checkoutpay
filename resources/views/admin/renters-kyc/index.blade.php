@extends('layouts.admin')

@section('title', 'Renters KYC')
@section('page-title', 'Renters KYC')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="text-sm text-gray-600">Review government ID uploads</div>
                <div class="text-xs text-gray-500">KYC is only Verified when bank is verified and ID is approved.</div>
            </div>

            <form method="GET" action="{{ route('admin.renters-kyc.index') }}" class="flex items-center gap-2">
                <label class="text-xs text-gray-600">Status</label>
                <select name="status" class="border border-gray-300 rounded-md text-sm px-3 py-2">
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
                <button class="px-3 py-2 rounded-md bg-gray-900 text-white text-sm">Filter</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700">Renter</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700">ID Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700">ID Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700">Reviewed</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($renters as $renter)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900 font-semibold">
                                {{ $renter->name }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                {{ $renter->email }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                {{ $renter->kyc_id_type ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $s = $renter->kyc_id_status ?: 'pending';
                                    $badgeBg = $s === 'approved' ? 'bg-green-100 text-green-800' : ($s === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800');
                                @endphp
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold {{ $badgeBg }}">
                                    {{ ucfirst($s) }}
                                </span>
                                @if($s === 'rejected' && $renter->kyc_id_rejection_reason)
                                    <div class="text-xs text-gray-500 mt-1">{{ $renter->kyc_id_rejection_reason }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                @if($renter->kyc_id_reviewed_at)
                                    <div class="text-xs text-gray-600">{{ $renter->kyc_id_reviewed_at->format('Y-m-d H:i') }}</div>
                                @else
                                    <span class="text-xs text-gray-500">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.renters-kyc.approve', $renter) }}">
                                        @csrf
                                        <button class="px-3 py-2 rounded-md bg-green-600 text-white text-sm">Approve</button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.renters-kyc.reject', $renter) }}" class="flex items-center gap-2">
                                        @csrf
                                        <input name="reason" placeholder="Reason (optional)" class="border border-gray-300 rounded-md text-sm px-3 py-2 w-56" />
                                        <button class="px-3 py-2 rounded-md bg-red-600 text-white text-sm">Reject</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No renters found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">
            {{ $renters->links() }}
        </div>
    </div>
</div>
@endsection


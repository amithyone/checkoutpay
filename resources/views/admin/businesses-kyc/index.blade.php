@extends('layouts.admin')

@section('title', 'Business KYC')
@section('page-title', 'Business KYC')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-id-card text-primary mr-2"></i> Business KYC Queue
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    Review and manage KYC documents for businesses.
                </p>
            </div>

            <form method="GET" action="{{ route('admin.businesses-kyc.index') }}" class="flex flex-wrap items-center gap-2">
                <label class="text-xs text-gray-600">Filter</label>
                <select name="status" class="border border-gray-300 rounded-md text-sm px-3 py-2">
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending / Under review</option>
                    <option value="verified" {{ $status === 'verified' ? 'selected' : '' }}>Verified (all approved)</option>
                    <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    <option value="not_submitted" {{ $status === 'not_submitted' ? 'selected' : '' }}>Not submitted</option>
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All</option>
                </select>
                <button type="submit" class="px-3 py-2 rounded-md bg-gray-900 text-white text-sm">
                    Filter
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($businesses->isEmpty())
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">
            No businesses found for this filter.
        </div>
    @else
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">KYC Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Missing</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($businesses as $business)
                            @php
                                $meta = $businessKycMeta[$business->id] ?? null;
                                $computed = $meta['computed_status'] ?? 'pending';
                                $missingCount = $meta['missing_count'] ?? 0;
                                $badgeClass = match($computed) {
                                    'verified' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    'under_review' => 'bg-yellow-100 text-yellow-800',
                                    default => 'bg-amber-100 text-amber-800',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900">{{ $business->name }}</div>
                                    <div class="text-xs text-gray-500 mt-1">Created {{ $business->created_at->format('M d, Y') }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $business->email }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $badgeClass }}">
                                        {{ ucfirst(str_replace('_', ' ', $computed)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    @if($computed === 'verified')
                                        —
                                    @else
                                        {{ $missingCount }} / {{ $requiredTypeCount }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.businesses.show', $business) }}"
                                       class="px-3 py-1.5 rounded-lg bg-primary text-white text-xs font-semibold hover:bg-primary/90 inline-flex items-center gap-2">
                                        <i class="fas fa-clipboard-check"></i> Manage KYC
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-gray-200">
                {{ $businesses->links() }}
            </div>
        </div>
    @endif
</div>
@endsection


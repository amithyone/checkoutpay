@extends('layouts.admin')

@section('title', 'Businesses')
@section('page-title', 'Businesses')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Manage Businesses</h3>
            <p class="text-sm text-gray-600 mt-1">View and manage all registered businesses</p>
        </div>
        <a href="{{ route('admin.businesses.create') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 flex items-center">
            <i class="fas fa-plus mr-2"></i> Add Business
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" action="{{ route('admin.businesses.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, email, website..." 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary focus:border-primary">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary focus:border-primary">
                    <option value="">All</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Website Status</label>
                <select name="website_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary focus:border-primary">
                    <option value="">All</option>
                    <option value="approved" {{ request('website_status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="pending" {{ request('website_status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="none" {{ request('website_status') === 'none' ? 'selected' : '' }}>No Website</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">KYC Status</label>
                <select name="kyc_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary focus:border-primary">
                    <option value="">All</option>
                    <option value="verified" {{ request('kyc_status') === 'verified' ? 'selected' : '' }}>Verified</option>
                    <option value="pending" {{ request('kyc_status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="not_submitted" {{ request('kyc_status') === 'not_submitted' ? 'selected' : '' }}>Not Submitted</option>
                </select>
            </div>
            <div class="md:col-span-4 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                @if(request()->hasAny(['search', 'status', 'website_status', 'kyc_status']))
                    <a href="{{ route('admin.businesses.index') }}" class="ml-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Website</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Balance</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($businesses as $business)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $business->name }}</div>
                            <div class="text-xs text-gray-500 mt-1">
                                <span class="inline-flex items-center">
                                    @if($business->verifications_count > 0)
                                        @php
                                            $latestVerification = $business->verifications->first();
                                        @endphp
                                        @if($latestVerification && $latestVerification->status === 'approved')
                                            <i class="fas fa-check-circle text-green-500 mr-1"></i>
                                            <span class="text-green-600">KYC Verified</span>
                                        @elseif($latestVerification && in_array($latestVerification->status, ['pending', 'under_review']))
                                            <i class="fas fa-clock text-yellow-500 mr-1"></i>
                                            <span class="text-yellow-600">KYC Pending</span>
                                        @else
                                            <i class="fas fa-times-circle text-red-500 mr-1"></i>
                                            <span class="text-red-600">KYC Rejected</span>
                                        @endif
                                    @else
                                        <i class="fas fa-exclamation-circle text-gray-400 mr-1"></i>
                                        <span class="text-gray-500">No KYC</span>
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $business->email }}</td>
                        <td class="px-6 py-4">
                            @if($business->website)
                                <div class="flex items-center space-x-2">
                                    <a href="{{ $business->website }}" target="_blank" class="text-sm text-primary hover:underline truncate max-w-xs">
                                        {{ Str::limit($business->website, 30) }}
                                    </a>
                                    @if($business->website_approved)
                                        <span class="px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                                    @else
                                        <span class="px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-gray-400">No website</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">₦{{ number_format($business->balance, 2) }}</td>
                        <td class="px-6 py-4">
                            @if($business->is_active)
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <a href="{{ route('admin.businesses.show', $business) }}" class="text-primary hover:underline mr-3">View</a>
                            <a href="{{ route('admin.businesses.edit', $business) }}" class="text-primary hover:underline">Edit</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No businesses found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($businesses->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $businesses->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

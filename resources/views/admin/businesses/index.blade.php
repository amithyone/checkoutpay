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

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Balance</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payments</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($businesses as $business)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $business->name }}</div>
                            <div class="text-xs text-gray-500">{{ $business->phone ?? 'No phone' }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $business->email }}</td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">₦{{ number_format($business->balance, 2) }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $business->payments_count }}</td>
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

@extends('layouts.renter')

@section('title', 'My Rentals')

@section('content')
<div>
    <h1 class="text-2xl font-bold mb-6">My Rentals</h1>

    <!-- User Info -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Account Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-600">Name</p>
                <p class="font-semibold">{{ $renter->name }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Email</p>
                <p class="font-semibold">{{ $renter->email }}</p>
            </div>
            @if($renter->phone)
                <div>
                    <p class="text-sm text-gray-600">Phone</p>
                    <p class="font-semibold">{{ $renter->phone }}</p>
                </div>
            @endif
            @if($renter->isKycVerified())
                <div>
                    <p class="text-sm text-gray-600">Verified Account</p>
                    <p class="font-semibold text-green-600">{{ $renter->verified_account_name }} - {{ $renter->verified_account_number }}</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-gray-600 text-sm">Total Rentals</p>
            <p class="text-2xl font-bold">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-gray-600 text-sm">Pending</p>
            <p class="text-2xl font-bold text-yellow-600">{{ $stats['pending'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-gray-600 text-sm">Approved</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['approved'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-gray-600 text-sm">Active</p>
            <p class="text-2xl font-bold text-blue-600">{{ $stats['active'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-gray-600 text-sm">Completed</p>
            <p class="text-2xl font-bold text-gray-600">{{ $stats['completed'] }}</p>
        </div>
    </div>

    <!-- Rentals List -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rental #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($rentals as $rental)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-medium">{{ $rental->rental_number }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <p class="font-medium">{{ $rental->business->name }}</p>
                                @if($rental->business_phone)
                                    <p class="text-sm text-gray-500"><i class="fas fa-phone"></i> {{ $rental->business_phone }}</p>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <p class="text-sm">{{ $rental->start_date->format('M d') }} - {{ $rental->end_date->format('M d, Y') }}</p>
                                <p class="text-xs text-gray-500">{{ $rental->days }} days</p>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-semibold">₦{{ number_format($rental->total_amount, 2) }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $statusColors = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'active' => 'bg-blue-100 text-blue-800',
                                    'completed' => 'bg-gray-100 text-gray-800',
                                    'cancelled' => 'bg-red-100 text-red-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                ];
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs font-medium {{ $statusColors[$rental->status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ ucfirst($rental->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="{{ route('renter.dashboard.show', $rental) }}" class="text-primary hover:underline">
                                View Details
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                            No rentals found. <a href="{{ route('rentals.index') }}" class="text-primary hover:underline">Browse Rentals</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $rentals->links() }}
    </div>
</div>
@endsection

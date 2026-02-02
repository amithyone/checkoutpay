@extends('layouts.business')

@section('title', 'Rentals')

@section('content')
<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Rentals</h1>
        <div class="flex gap-2">
            <a href="{{ route('business.rentals.items.create') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90">
                <i class="fas fa-plus mr-2"></i> Add Item
            </a>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
                <a href="{{ route('business.rentals.index') }}" class="px-6 py-4 text-sm font-medium border-b-2 {{ request()->routeIs('business.rentals.index') ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    <i class="fas fa-list mr-2"></i> Rental Requests
                </a>
                <a href="{{ route('business.rentals.items') }}" class="px-6 py-4 text-sm font-medium border-b-2 {{ request()->routeIs('business.rentals.items*') ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    <i class="fas fa-box mr-2"></i> My Items
                </a>
            </nav>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-gray-600 text-sm">Total</p>
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

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Status</label>
                <select name="status" class="w-full border-gray-300 rounded-md">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Rental #, renter name..." class="w-full border-gray-300 rounded-md">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-primary text-white px-4 py-2 rounded-md hover:bg-primary/90">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Rentals Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rental #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Renter</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
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
                                <p class="font-medium">{{ $rental->renter_name }}</p>
                                <p class="text-sm text-gray-500">{{ $rental->renter_email }}</p>
                                @if($rental->renter_phone)
                                    <p class="text-xs text-gray-500"><i class="fas fa-phone"></i> {{ $rental->renter_phone }}</p>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm">
                                @if($rental->items->count() > 0)
                                    <p class="font-medium">{{ $rental->items->count() }} item(s)</p>
                                    <p class="text-xs text-gray-500">{{ $rental->items->pluck('name')->take(2)->implode(', ') }}{!! $rental->items->count() > 2 ? '...' : '' !!}</p>
                                @else
                                    <span class="text-gray-400">No items</span>
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
                            <div class="flex gap-2">
                                <a href="{{ route('business.rentals.show', $rental) }}" class="text-primary hover:underline">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                @if($rental->status === 'pending')
                                    <form action="{{ route('business.rentals.approve', $rental) }}" method="POST" class="inline" onsubmit="return confirm('Approve this rental?');">
                                        @csrf
                                        <button type="submit" class="text-green-600 hover:underline">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                            <div class="py-8">
                                <i class="fas fa-inbox text-gray-400 text-4xl mb-2"></i>
                                <p class="text-gray-600">No rental requests found.</p>
                                <p class="text-sm text-gray-500 mt-1">Rental requests from customers will appear here.</p>
                            </div>
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

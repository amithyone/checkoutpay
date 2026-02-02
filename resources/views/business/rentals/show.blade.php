@extends('layouts.business')

@section('title', 'Rental Details')

@section('content')
<div class="p-6">
    <a href="{{ route('business.rentals.index') }}" class="text-primary hover:underline mb-4 inline-block">
        <i class="fas fa-arrow-left"></i> Back to Rentals
    </a>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-2xl font-bold mb-2">Rental #{{ $rental->rental_number }}</h1>
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
                <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$rental->status] ?? 'bg-gray-100 text-gray-800' }}">
                    {{ ucfirst($rental->status) }}
                </span>
            </div>
        </div>

        <!-- Renter Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <h3 class="font-semibold mb-2">Renter Information</h3>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p><strong>Name:</strong> {{ $rental->renter_name }}</p>
                    <p><strong>Email:</strong> {{ $rental->renter_email }}</p>
                    @if($rental->renter_phone)
                        <p><strong>Phone:</strong> {{ $rental->renter_phone }}</p>
                    @endif
                    @if($rental->renter_address)
                        <p><strong>Address:</strong> {{ $rental->renter_address }}</p>
                    @endif
                </div>
            </div>
            <div>
                <h3 class="font-semibold mb-2">Verified Account</h3>
                <div class="bg-green-50 p-4 rounded-lg">
                    <p><strong>Account Name:</strong> {{ $rental->verified_account_name }}</p>
                    <p><strong>Account Number:</strong> {{ $rental->verified_account_number }}</p>
                    <p><strong>Bank:</strong> {{ $rental->verified_bank_name }}</p>
                </div>
            </div>
        </div>

        <!-- Rental Period -->
        <div class="mb-6">
            <h3 class="font-semibold mb-2">Rental Period</h3>
            <div class="bg-gray-50 p-4 rounded-lg">
                <p><strong>Start Date:</strong> {{ $rental->start_date->format('F d, Y') }}</p>
                <p><strong>End Date:</strong> {{ $rental->end_date->format('F d, Y') }}</p>
                <p><strong>Duration:</strong> {{ $rental->days }} days</p>
            </div>
        </div>

        <!-- Items -->
        <div class="mb-6">
            <h3 class="font-semibold mb-2">Items to be Rented</h3>
            <div class="space-y-4">
                @foreach($rental->items as $item)
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="flex justify-between">
                            <div>
                                <p class="font-semibold">{{ $item->name }}</p>
                                <p class="text-sm text-gray-600">Quantity: {{ $item->pivot->quantity }}</p>
                                <p class="text-sm text-gray-600">Rate: ₦{{ number_format($item->pivot->unit_rate, 2) }}/day</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold">₦{{ number_format($item->pivot->total_amount, 2) }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Total -->
        <div class="mb-6">
            <div class="bg-primary/10 p-4 rounded-lg text-right">
                <p class="text-2xl font-bold">Total: ₦{{ number_format($rental->total_amount, 2) }}</p>
            </div>
        </div>

        @if($rental->renter_notes)
            <div class="mb-6">
                <h3 class="font-semibold mb-2">Renter Notes</h3>
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <p>{{ $rental->renter_notes }}</p>
                </div>
            </div>
        @endif

        <!-- Actions -->
        <div class="border-t pt-6">
            <h3 class="font-semibold mb-4">Update Status</h3>
            <form action="{{ route('business.rentals.update-status', $rental) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium mb-1">Status</label>
                    <select name="status" required class="w-full border-gray-300 rounded-md">
                        <option value="pending" {{ $rental->status == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ $rental->status == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="active" {{ $rental->status == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="completed" {{ $rental->status == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="cancelled" {{ $rental->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        <option value="rejected" {{ $rental->status == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90">
                    Update Status
                </button>
            </form>
        </div>

        @if($rental->status === 'pending')
            <div class="mt-6 flex gap-4">
                <form action="{{ route('business.rentals.approve', $rental) }}" method="POST" class="flex-1">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Notes (Optional)</label>
                        <textarea name="business_notes" rows="3" class="w-full border-gray-300 rounded-md"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700">
                        Approve Rental
                    </button>
                </form>
                <form action="{{ route('business.rentals.reject', $rental) }}" method="POST" class="flex-1">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Rejection Reason (Required)</label>
                        <textarea name="business_notes" rows="3" required class="w-full border-gray-300 rounded-md"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700">
                        Reject Rental
                    </button>
                </form>
            </div>
        @endif

        @if($rental->business_notes)
            <div class="mt-6">
                <h3 class="font-semibold mb-2">Your Notes</h3>
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p>{{ $rental->business_notes }}</p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

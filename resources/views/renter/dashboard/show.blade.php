@extends('layouts.renter')

@section('title', 'Rental Details')

@section('content')
<div>
    <a href="{{ route('renter.dashboard') }}" class="text-primary hover:underline mb-4 inline-block">
        <i class="fas fa-arrow-left"></i> Back to My Rentals
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

        <!-- Business Info -->
        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
            <h3 class="font-semibold mb-2">Business</h3>
            <p><strong>Name:</strong> {{ $rental->business->name }}</p>
            <p><strong>Email:</strong> {{ $rental->business->email }}</p>
            @if($rental->business_phone)
                <p><strong>Phone:</strong> <a href="tel:{{ $rental->business_phone }}" class="text-primary hover:underline">{{ $rental->business_phone }}</a></p>
            @endif
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
                                <p class="text-sm text-gray-600">Category: {{ $item->category->name }}</p>
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
                <h3 class="font-semibold mb-2">Your Notes</h3>
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <p>{{ $rental->renter_notes }}</p>
                </div>
            </div>
        @endif

        @if($rental->business_notes)
            <div class="mb-6">
                <h3 class="font-semibold mb-2">Business Notes</h3>
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p>{{ $rental->business_notes }}</p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

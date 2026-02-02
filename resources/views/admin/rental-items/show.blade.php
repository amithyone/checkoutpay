@extends('layouts.admin')

@section('title', 'Rental Item Details')

@section('content')
<div class="p-6">
    <a href="{{ route('admin.rental-items.index') }}" class="text-primary hover:underline mb-4 inline-block">
        <i class="fas fa-arrow-left"></i> Back to Items
    </a>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-2xl font-bold mb-2">{{ $rentalItem->name }}</h1>
                <div class="flex items-center gap-4">
                    <span class="bg-primary/10 text-primary px-3 py-1 rounded-full text-sm">{{ $rentalItem->category->name }}</span>
                    @if($rentalItem->is_active && $rentalItem->is_available)
                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                    @elseif(!$rentalItem->is_active)
                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Inactive</span>
                    @else
                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Unavailable</span>
                    @endif
                </div>
            </div>
            <a href="{{ route('admin.rental-items.edit', $rentalItem) }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90">
                Edit Item
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <h3 class="font-semibold mb-2">Business</h3>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p><strong>Name:</strong> <a href="{{ route('admin.businesses.show', $rentalItem->business_id) }}" class="text-primary hover:underline">{{ $rentalItem->business->name }}</a></p>
                    <p><strong>Email:</strong> {{ $rentalItem->business->email }}</p>
                </div>
            </div>
            <div>
                <h3 class="font-semibold mb-2">Location</h3>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p><strong>City:</strong> {{ $rentalItem->city ?? 'N/A' }}</p>
                    <p><strong>State:</strong> {{ $rentalItem->state ?? 'N/A' }}</p>
                    @if($rentalItem->address)
                        <p><strong>Address:</strong> {{ $rentalItem->address }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="mb-6">
            <h3 class="font-semibold mb-2">Description</h3>
            <div class="bg-gray-50 p-4 rounded-lg">
                <p>{{ $rentalItem->description ?? 'No description provided.' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div>
                <h3 class="font-semibold mb-2">Pricing</h3>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p><strong>Daily:</strong> ₦{{ number_format($rentalItem->daily_rate, 2) }}</p>
                    @if($rentalItem->weekly_rate)
                        <p><strong>Weekly:</strong> ₦{{ number_format($rentalItem->weekly_rate, 2) }}</p>
                    @endif
                    @if($rentalItem->monthly_rate)
                        <p><strong>Monthly:</strong> ₦{{ number_format($rentalItem->monthly_rate, 2) }}</p>
                    @endif
                </div>
            </div>
            <div>
                <h3 class="font-semibold mb-2">Availability</h3>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p><strong>Quantity:</strong> {{ $rentalItem->quantity_available }}</p>
                    <p><strong>Status:</strong> {{ $rentalItem->is_available ? 'Available' : 'Unavailable' }}</p>
                </div>
            </div>
            <div>
                <h3 class="font-semibold mb-2">Settings</h3>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p><strong>Active:</strong> {{ $rentalItem->is_active ? 'Yes' : 'No' }}</p>
                    <p><strong>Featured:</strong> {{ $rentalItem->is_featured ? 'Yes' : 'No' }}</p>
                </div>
            </div>
        </div>

        @if($rentalItem->images && count($rentalItem->images) > 0)
            <div class="mb-6">
                <h3 class="font-semibold mb-2">Images</h3>
                <div class="grid grid-cols-4 gap-4">
                    @foreach($rentalItem->images as $image)
                        <img src="{{ asset('storage/' . $image) }}" alt="{{ $rentalItem->name }}" class="w-full h-32 object-cover rounded">
                    @endforeach
                </div>
            </div>
        @endif

        @if($rentalItem->specifications)
            <div class="mb-6">
                <h3 class="font-semibold mb-2">Specifications</h3>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($rentalItem->specifications as $key => $value)
                            <li><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

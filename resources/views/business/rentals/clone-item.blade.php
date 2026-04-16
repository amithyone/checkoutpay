@extends('layouts.business')

@section('title', 'Clone Item')

@section('content')
<div class="p-6 max-w-3xl">
    <a href="{{ route('business.rentals.items.catalog') }}" class="text-primary hover:underline mb-4 inline-block">
        <i class="fas fa-arrow-left"></i> Back to Catalog
    </a>

    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold mb-2">Clone Item</h1>
        <p class="text-sm text-gray-600 mb-6">
            You can only edit the description while cloning. After saving, this becomes your own item.
        </p>

        <div class="border border-gray-200 rounded-lg p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="text-xs text-gray-500">Name</div>
                    <div class="font-medium">{{ $item->name }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Category</div>
                    <div class="font-medium">{{ $item->category?->name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Daily rate</div>
                    <div class="font-medium">₦{{ number_format($item->daily_rate, 2) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Quantity</div>
                    <div class="font-medium">{{ $item->quantity_available }}</div>
                </div>
                <div class="md:col-span-2">
                    <div class="text-xs text-gray-500">Location</div>
                    <div class="font-medium">{{ $item->city ?? '—' }}{{ $item->state ? ', ' . $item->state : '' }}</div>
                </div>
            </div>
        </div>

        <form action="{{ route('business.rentals.items.clone.store', $item) }}" method="POST">
            @csrf

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Description (editable)</label>
                <textarea name="description" rows="5" class="w-full border-gray-300 rounded-md">{{ old('description', $item->description) }}</textarea>
                <p class="text-xs text-gray-500 mt-1">All other fields will be copied as-is.</p>
            </div>

            <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg hover:bg-primary/90 font-medium">
                Save as My Item
            </button>
        </form>
    </div>
</div>
@endsection


@extends('layouts.business')

@section('title', 'Edit Rental Item')

@section('content')
<div class="p-6">
    <a href="{{ route('business.rentals.items') }}" class="text-primary hover:underline mb-4 inline-block">
        <i class="fas fa-arrow-left"></i> Back to Items
    </a>

    <div class="bg-white rounded-lg shadow p-6 max-w-3xl">
        <h1 class="text-2xl font-bold mb-6">Edit Rental Item</h1>

        <form action="{{ route('business.rentals.items.update', $item) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Category *</label>
                    <select name="category_id" required class="w-full border-gray-300 rounded-md">
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id', $item->category_id) == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Item Name *</label>
                    <input type="text" name="name" required class="w-full border-gray-300 rounded-md" value="{{ old('name', $item->name) }}">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Description</label>
                <textarea name="description" rows="3" class="w-full border-gray-300 rounded-md">{{ old('description', $item->description) }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">City</label>
                    <select name="city" class="w-full border-gray-300 rounded-md">
                        <option value="">Select City</option>
                        @foreach(config('cities.major_cities', []) as $city)
                            <option value="{{ $city }}" {{ old('city', $item->city) == $city ? 'selected' : '' }}>{{ $city }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">State</label>
                    <input type="text" name="state" class="w-full border-gray-300 rounded-md" value="{{ old('state', $item->state) }}">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Quantity Available *</label>
                    <input type="number" name="quantity_available" value="{{ old('quantity_available', $item->quantity_available) }}" min="1" required class="w-full border-gray-300 rounded-md">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Daily Rate (₦) *</label>
                    <input type="number" name="daily_rate" step="0.01" min="0" required class="w-full border-gray-300 rounded-md" value="{{ old('daily_rate', $item->daily_rate) }}">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Weekly Rate (₦)</label>
                    <input type="number" name="weekly_rate" step="0.01" min="0" class="w-full border-gray-300 rounded-md" value="{{ old('weekly_rate', $item->weekly_rate) }}">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Monthly Rate (₦)</label>
                    <input type="number" name="monthly_rate" step="0.01" min="0" class="w-full border-gray-300 rounded-md" value="{{ old('monthly_rate', $item->monthly_rate) }}">
                </div>
            </div>

            @if($item->images && count($item->images) > 0)
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Current Images</label>
                    <div class="grid grid-cols-4 gap-4">
                        @foreach($item->images as $image)
                            <div class="relative">
                                <img src="{{ asset('storage/' . $image) }}" alt="Image" class="w-full h-24 object-cover rounded">
                                <label class="absolute top-1 right-1 bg-red-500 text-white px-2 py-1 rounded text-xs cursor-pointer">
                                    <input type="checkbox" name="remove_images[]" value="{{ $image }}" class="hidden" onchange="this.parentElement.style.display='none'">
                                    Remove
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Add New Images</label>
                <input type="file" name="images[]" multiple accept="image/*" class="w-full border-gray-300 rounded-md">
                <p class="text-xs text-gray-500 mt-1">You can select multiple images</p>
            </div>

            <div class="grid grid-cols-3 gap-4 mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $item->is_featured) ? 'checked' : '' }} class="mr-2">
                    <span>Featured</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $item->is_active) ? 'checked' : '' }} class="mr-2">
                    <span>Active</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="is_available" value="1" {{ old('is_available', $item->is_available) ? 'checked' : '' }} class="mr-2">
                    <span>Available</span>
                </label>
            </div>

            <div class="flex gap-4">
                <button type="submit" class="flex-1 bg-primary text-white py-3 rounded-lg hover:bg-primary/90 font-medium">
                    Update Item
                </button>
                <form action="{{ route('business.rentals.items.destroy', $item) }}" method="POST" class="flex-1" onsubmit="return confirm('Are you sure you want to delete this item?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full bg-red-600 text-white py-3 rounded-lg hover:bg-red-700 font-medium">
                        Delete Item
                    </button>
                </form>
            </div>
        </form>
    </div>
</div>
@endsection

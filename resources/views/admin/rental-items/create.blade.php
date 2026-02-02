@extends('layouts.admin')

@section('title', 'Create Rental Item')

@section('content')
<div class="p-6">
    <a href="{{ route('admin.rental-items.index') }}" class="text-primary hover:underline mb-4 inline-block">
        <i class="fas fa-arrow-left"></i> Back to Items
    </a>

    <div class="bg-white rounded-lg shadow p-6 max-w-4xl">
        <h1 class="text-2xl font-bold mb-6">Create Rental Item</h1>

        <form action="{{ route('admin.rental-items.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Business *</label>
                    <select name="business_id" required class="w-full border-gray-300 rounded-md">
                        <option value="">Select Business</option>
                        @foreach($businesses as $business)
                            <option value="{{ $business->id }}" {{ old('business_id') == $business->id ? 'selected' : '' }}>
                                {{ $business->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('business_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Category *</label>
                    <select name="category_id" required class="w-full border-gray-300 rounded-md">
                        <option value="">Select Category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Item Name *</label>
                <input type="text" name="name" required class="w-full border-gray-300 rounded-md" value="{{ old('name') }}">
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Description</label>
                <textarea name="description" rows="3" class="w-full border-gray-300 rounded-md">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">City</label>
                    <select name="city" class="w-full border-gray-300 rounded-md">
                        <option value="">Select City</option>
                        @foreach(config('cities.major_cities', []) as $city)
                            <option value="{{ $city }}" {{ old('city') == $city ? 'selected' : '' }}>{{ $city }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">State</label>
                    <input type="text" name="state" class="w-full border-gray-300 rounded-md" value="{{ old('state') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Quantity Available *</label>
                    <input type="number" name="quantity_available" value="{{ old('quantity_available', 1) }}" min="1" required class="w-full border-gray-300 rounded-md">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Daily Rate (₦) *</label>
                    <input type="number" name="daily_rate" step="0.01" min="0" required class="w-full border-gray-300 rounded-md" value="{{ old('daily_rate') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Weekly Rate (₦)</label>
                    <input type="number" name="weekly_rate" step="0.01" min="0" class="w-full border-gray-300 rounded-md" value="{{ old('weekly_rate') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Monthly Rate (₦)</label>
                    <input type="number" name="monthly_rate" step="0.01" min="0" class="w-full border-gray-300 rounded-md" value="{{ old('monthly_rate') }}">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Images</label>
                <input type="file" name="images[]" multiple accept="image/*" class="w-full border-gray-300 rounded-md">
                <p class="text-xs text-gray-500 mt-1">You can select multiple images</p>
            </div>

            <div class="grid grid-cols-3 gap-4 mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_featured" value="1" {{ old('is_featured') ? 'checked' : '' }} class="mr-2">
                    <span>Featured</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="mr-2">
                    <span>Active</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="is_available" value="1" {{ old('is_available', true) ? 'checked' : '' }} class="mr-2">
                    <span>Available</span>
                </label>
            </div>

            <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg hover:bg-primary/90 font-medium">
                Create Item
            </button>
        </form>
    </div>
</div>
@endsection

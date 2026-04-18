@extends('layouts.admin')

@section('title', 'Edit Rental Item')

@section('content')
<div class="p-4">
    <a href="{{ route('admin.rental-items.index') }}" class="text-xs text-primary hover:underline mb-2 inline-block">
        <i class="fas fa-arrow-left"></i> Back to items
    </a>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4 max-w-4xl">
        <div class="flex flex-wrap justify-between items-start gap-2 mb-4">
            <h1 class="text-lg font-bold text-gray-900">Edit rental item</h1>
            <form action="{{ route('admin.rental-items.destroy', $rentalItem) }}" method="POST" onsubmit="return confirm('Delete this rental item permanently? Images will be removed from storage.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-xs bg-red-600 text-white px-2.5 py-1.5 rounded-md hover:bg-red-700 font-medium">
                    <i class="fas fa-trash-alt mr-1"></i>Delete item
                </button>
            </form>
        </div>

        <form action="{{ route('admin.rental-items.update', $rentalItem) }}" method="POST" enctype="multipart/form-data" class="space-y-3">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-0.5">Business *</label>
                    <select name="business_id" required class="w-full text-sm border-gray-300 rounded-md py-1.5">
                        @foreach($businesses as $business)
                            <option value="{{ $business->id }}" {{ old('business_id', $rentalItem->business_id) == $business->id ? 'selected' : '' }}>
                                {{ $business->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-0.5">Category *</label>
                    <select name="category_id" required class="w-full text-sm border-gray-300 rounded-md py-1.5">
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id', $rentalItem->category_id) == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-0.5">Name *</label>
                <input type="text" name="name" required class="w-full text-sm border-gray-300 rounded-md py-1.5" value="{{ old('name', $rentalItem->name) }}">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-0.5">Description</label>
                <textarea name="description" rows="2" class="w-full text-sm border-gray-300 rounded-md py-1.5">{{ old('description', $rentalItem->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-0.5">City</label>
                    <select name="city" class="w-full text-sm border-gray-300 rounded-md py-1.5">
                        <option value="">—</option>
                        @foreach(config('cities.major_cities', []) as $city)
                            <option value="{{ $city }}" {{ old('city', $rentalItem->city) == $city ? 'selected' : '' }}>{{ $city }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-0.5">State</label>
                    <input type="text" name="state" class="w-full text-sm border-gray-300 rounded-md py-1.5" value="{{ old('state', $rentalItem->state) }}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-0.5">Qty *</label>
                    <input type="number" name="quantity_available" value="{{ old('quantity_available', $rentalItem->quantity_available) }}" min="1" required class="w-full text-sm border-gray-300 rounded-md py-1.5">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-0.5">Daily (₦) *</label>
                    <input type="number" name="daily_rate" step="0.01" min="0" required class="w-full text-sm border-gray-300 rounded-md py-1.5" value="{{ old('daily_rate', $rentalItem->daily_rate) }}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-0.5">Weekly (₦)</label>
                    <input type="number" name="weekly_rate" step="0.01" min="0" class="w-full text-sm border-gray-300 rounded-md py-1.5" value="{{ old('weekly_rate', $rentalItem->weekly_rate) }}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-0.5">Monthly (₦)</label>
                    <input type="number" name="monthly_rate" step="0.01" min="0" class="w-full text-sm border-gray-300 rounded-md py-1.5" value="{{ old('monthly_rate', $rentalItem->monthly_rate) }}">
                </div>
            </div>

            <div class="border border-emerald-100 rounded-md p-3 bg-emerald-50/40">
                <div class="text-xs font-semibold text-emerald-900 mb-2">Discount</div>
                <label class="inline-flex items-center gap-1.5 mb-2">
                    <input type="checkbox" name="discount_active" value="1" {{ old('discount_active', $rentalItem->discount_active) ? 'checked' : '' }} class="rounded">
                    <span class="text-xs">Active</span>
                </label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                    <div>
                        <label class="block text-[10px] font-medium text-gray-600 mb-0.5">% off</label>
                        <input type="number" name="discount_percent" step="0.01" min="0" max="95" class="w-full text-sm border-gray-300 rounded-md py-1.5" value="{{ old('discount_percent', $rentalItem->discount_percent) }}">
                    </div>
                    <div>
                        <label class="block text-[10px] font-medium text-gray-600 mb-0.5">Start</label>
                        <input type="date" name="discount_starts_at" class="w-full text-sm border-gray-300 rounded-md py-1.5" value="{{ old('discount_starts_at', optional($rentalItem->discount_starts_at)->format('Y-m-d'))) }}">
                    </div>
                    <div>
                        <label class="block text-[10px] font-medium text-gray-600 mb-0.5">End</label>
                        <input type="date" name="discount_ends_at" class="w-full text-sm border-gray-300 rounded-md py-1.5" value="{{ old('discount_ends_at', optional($rentalItem->discount_ends_at)->format('Y-m-d'))) }}">
                    </div>
                </div>
            </div>

            @if($rentalItem->images && count($rentalItem->images) > 0)
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Photos — tick to remove on save</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach($rentalItem->images as $image)
                            <div class="relative border border-gray-200 rounded-md overflow-hidden w-20 shrink-0">
                                <img src="{{ asset('storage/' . $image) }}" alt="" class="w-full h-16 object-cover">
                                <label class="flex items-center justify-center gap-1 bg-gray-50 px-1 py-0.5 text-[10px] text-red-600 cursor-pointer border-t border-gray-200">
                                    <input type="checkbox" name="remove_images[]" value="{{ $image }}" class="rounded text-red-600">
                                    <span>Remove</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-0.5">Add images</label>
                <input type="file" name="images[]" multiple accept="image/*" class="w-full text-sm border-gray-300 rounded-md py-1">
                <p class="text-[10px] text-gray-500 mt-0.5">Multiple files allowed.</p>
            </div>

            <div class="flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-1.5">
                    <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $rentalItem->is_featured) ? 'checked' : '' }} class="rounded">
                    <span class="text-xs">Featured</span>
                </label>
                <label class="inline-flex items-center gap-1.5">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $rentalItem->is_active) ? 'checked' : '' }} class="rounded">
                    <span class="text-xs">Active</span>
                </label>
                <label class="inline-flex items-center gap-1.5">
                    <input type="checkbox" name="is_available" value="1" {{ old('is_available', $rentalItem->is_available) ? 'checked' : '' }} class="rounded">
                    <span class="text-xs">Available</span>
                </label>
            </div>

            <button type="submit" class="w-full text-sm bg-primary text-white py-2 rounded-md hover:bg-primary/90 font-medium">
                Save changes
            </button>
        </form>
    </div>
</div>
@endsection

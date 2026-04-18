@extends('layouts.business')

@section('title', 'Create Rental Item')

@section('content')
<div class="p-6">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('business.rentals.items') }}" class="text-primary hover:underline inline-block">
            <i class="fas fa-arrow-left"></i> Back to Items
        </a>
        <a href="{{ route('business.rentals.items.catalog') }}" class="inline-flex items-center px-3 py-1.5 text-sm border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
            <i class="fas fa-copy mr-2"></i> Clone Item
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 max-w-3xl">
        <h1 class="text-2xl font-bold mb-6">Create Rental Item</h1>

        <form action="{{ route('business.rentals.items.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Category *</label>
                    <select name="category_id" required class="w-full border border-gray-300 rounded-md">
                        <option value="">Select Category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Item Name *</label>
                    <input type="text" name="name" required class="w-full border border-gray-300 rounded-md">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Description</label>
                <textarea name="description" rows="3" class="w-full border border-gray-300 rounded-md"></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">City</label>
                    <select name="city" class="w-full border border-gray-300 rounded-md">
                        <option value="">Select City</option>
                        @foreach(config('cities.major_cities', []) as $city)
                            <option value="{{ $city }}">{{ $city }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">State</label>
                    <input type="text" name="state" class="w-full border border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Currency</label>
                    <input type="text" name="currency" value="NGN" maxlength="3" class="w-full border border-gray-300 rounded-md uppercase">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Quantity Available *</label>
                    <input type="number" name="quantity_available" value="1" min="1" required class="w-full border border-gray-300 rounded-md">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Address</label>
                <textarea name="address" rows="2" class="w-full border border-gray-300 rounded-md"></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Daily Rate (₦) *</label>
                    <input type="number" name="daily_rate" step="0.01" min="0" required class="w-full border border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Weekly Rate (₦)</label>
                    <input type="number" name="weekly_rate" step="0.01" min="0" class="w-full border border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Monthly Rate (₦)</label>
                    <input type="number" name="monthly_rate" step="0.01" min="0" class="w-full border border-gray-300 rounded-md">
                </div>
            </div>

            <div class="border border-emerald-100 rounded-lg p-4 mb-4 bg-emerald-50/40">
                <div class="font-semibold text-emerald-900 mb-1">Discount (optional)</div>
                <p class="text-xs text-gray-600 mb-3">Shoppers see a green &quot;On discount&quot; badge when your promo is active and within the dates.</p>
                <label class="flex items-center gap-2 mb-3">
                    <input type="checkbox" name="discount_active" value="1" {{ old('discount_active') ? 'checked' : '' }} class="rounded">
                    <span class="text-sm">Enable discount for this item</span>
                </label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Percent off (0–95)</label>
                        <input type="number" name="discount_percent" step="0.01" min="0" max="95" class="w-full border border-gray-300 rounded-md" value="{{ old('discount_percent') }}" placeholder="e.g. 15">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Starts</label>
                        <input type="date" name="discount_starts_at" class="w-full border border-gray-300 rounded-md" value="{{ old('discount_starts_at') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Ends</label>
                        <input type="date" name="discount_ends_at" class="w-full border border-gray-300 rounded-md" value="{{ old('discount_ends_at') }}">
                    </div>
                </div>
            </div>

            <div class="border border-gray-200 rounded-lg p-4 mb-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-semibold">Caution fee</div>
                        <div class="text-xs text-gray-500">Optional deposit charged as a percentage of the rental total.</div>
                    </div>
                    <label class="flex items-center">
                        <input type="checkbox" name="caution_fee_enabled" value="1" class="mr-2">
                        <span class="text-sm">Enable</span>
                    </label>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Caution fee (%)</label>
                        <input type="number" name="caution_fee_percent" step="0.01" min="0" max="100" value="0" class="w-full border border-gray-300 rounded-md">
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Images</label>
                <input type="file" name="images[]" multiple accept="image/*" class="w-full border border-gray-300 rounded-md">
                <p class="text-xs text-gray-500 mt-1">You can select multiple images</p>
            </div>

            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_featured" value="1" class="mr-2">
                    <span>Feature this item</span>
                </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" checked class="mr-2">
                    <span>Active</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="is_available" value="1" checked class="mr-2">
                    <span>Available</span>
                </label>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Terms and Conditions</label>
                <textarea name="terms_and_conditions" rows="3" class="w-full border border-gray-300 rounded-md" placeholder="Rental terms, damages policy, late return policy..."></textarea>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium mb-1">Specifications (JSON)</label>
                <textarea name="specifications_json" rows="4" class="w-full border border-gray-300 rounded-md font-mono text-sm" placeholder='{"brand":"Canon","model":"R5","sensor":"45MP"}'></textarea>
                <p class="text-xs text-gray-500 mt-1">Optional. Provide valid JSON object/array.</p>
            </div>

            <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg hover:bg-primary/90 font-medium">
                Create Item
            </button>
        </form>
    </div>
</div>
@endsection

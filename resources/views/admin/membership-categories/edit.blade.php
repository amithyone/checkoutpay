@extends('layouts.admin')

@section('title', 'Edit Membership Category')

@section('content')
<div class="p-6 max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">Edit Membership Category</h1>

    <form action="{{ route('admin.membership-categories.update', $membershipCategory) }}" method="POST" class="bg-white rounded-lg shadow p-6">
        @csrf
        @method('PUT')

        <div class="space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                <input type="text" name="name" id="name" required value="{{ old('name', $membershipCategory->name) }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" id="description" rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">{{ old('description', $membershipCategory->description) }}</textarea>
            </div>

            <div>
                <label for="icon" class="block text-sm font-medium text-gray-700 mb-1">Icon (Font Awesome class)</label>
                <input type="text" name="icon" id="icon" value="{{ old('icon', $membershipCategory->icon) }}" placeholder="e.g., fas fa-dumbbell"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                @if($membershipCategory->icon)
                    <p class="mt-1 text-xs text-gray-500">Current: <i class="{{ $membershipCategory->icon }}"></i></p>
                @endif
                <p class="mt-1 text-xs text-gray-500">Use Font Awesome icon classes (e.g., fas fa-dumbbell, fas fa-running)</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $membershipCategory->sort_order) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label class="flex items-center mt-6">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $membershipCategory->is_active) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-primary focus:ring-primary">
                        <span class="ml-2 text-sm text-gray-700">Active</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-4 pt-4">
                <a href="{{ route('admin.membership-categories.index') }}" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    Update Category
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

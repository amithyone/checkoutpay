@extends('layouts.admin')

@section('title', 'Create Rental Category')

@section('content')
<div class="p-6">
    <a href="{{ route('admin.rental-categories.index') }}" class="text-primary hover:underline mb-4 inline-block">
        <i class="fas fa-arrow-left"></i> Back to Categories
    </a>

    <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
        <h1 class="text-2xl font-bold mb-6">Create Rental Category</h1>

        <form action="{{ route('admin.rental-categories.store') }}" method="POST">
            @csrf

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Name *</label>
                <input type="text" name="name" required class="w-full border-gray-300 rounded-md" value="{{ old('name') }}">
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Description</label>
                <textarea name="description" rows="3" class="w-full border-gray-300 rounded-md">{{ old('description') }}</textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Icon (Font Awesome class)</label>
                <input type="text" name="icon" placeholder="e.g., fas fa-camera" class="w-full border-gray-300 rounded-md" value="{{ old('icon') }}">
                <p class="text-xs text-gray-500 mt-1">Example: fas fa-camera, fas fa-car, fas fa-building</p>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" class="w-full border-gray-300 rounded-md">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="mr-2">
                        <span>Active</span>
                    </label>
                </div>
            </div>

            <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg hover:bg-primary/90 font-medium">
                Create Category
            </button>
        </form>
    </div>
</div>
@endsection

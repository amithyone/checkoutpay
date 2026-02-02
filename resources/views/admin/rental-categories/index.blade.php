@extends('layouts.admin')

@section('title', 'Rental Categories')

@section('content')
<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Rental Categories</h1>
        <a href="{{ route('admin.rental-categories.create') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90">
            <i class="fas fa-plus mr-2"></i> Add Category
        </a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Icon</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sort Order</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($categories as $category)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-medium">{{ $category->name }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($category->icon)
                                <i class="{{ $category->icon }} text-primary"></i>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm">{{ $category->items()->count() }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm">{{ $category->sort_order }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($category->is_active)
                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="{{ route('admin.rental-categories.edit', $category) }}" class="text-primary hover:underline mr-3">Edit</a>
                            <form action="{{ route('admin.rental-categories.destroy', $category) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                            No categories found. <a href="{{ route('admin.rental-categories.create') }}" class="text-primary hover:underline">Create one</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

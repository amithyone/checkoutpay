@extends('layouts.business')

@section('title', 'Rental Items')

@section('content')
<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Rentals</h1>
        <div class="flex gap-2">
            <a href="{{ route('business.rentals.items.create') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90">
                <i class="fas fa-plus mr-2"></i> Add Item
            </a>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
                <a href="{{ route('business.rentals.index') }}" class="px-6 py-4 text-sm font-medium border-b-2 {{ request()->routeIs('business.rentals.index') ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    <i class="fas fa-list mr-2"></i> Rental Requests
                </a>
                <a href="{{ route('business.rentals.items') }}" class="px-6 py-4 text-sm font-medium border-b-2 {{ request()->routeIs('business.rentals.items*') ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    <i class="fas fa-box mr-2"></i> My Items
                </a>
            </nav>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Category</label>
                <select name="category_id" class="w-full border-gray-300 rounded-md">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Status</label>
                <select name="status" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="unavailable" {{ request('status') == 'unavailable' ? 'selected' : '' }}>Unavailable</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-primary text-white px-4 py-2 rounded-md">Filter</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">City</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Daily Rate</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Available</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($items as $item)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-medium">{{ $item->name }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm">{{ $item->category->name }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm">{{ $item->city ?? 'N/A' }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-semibold">₦{{ number_format($item->daily_rate, 2) }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm">{{ $item->quantity_available }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($item->is_active && $item->is_available)
                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                            @elseif(!$item->is_active)
                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Inactive</span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Unavailable</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex gap-2">
                                <a href="{{ route('business.rentals.items.edit', $item) }}" class="text-primary hover:underline">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <form action="{{ route('business.rentals.items.destroy', $item) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                            No rental items found. <a href="{{ route('business.rentals.items.create') }}" class="text-primary hover:underline">Create one</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $items->links() }}
    </div>
</div>
@endsection

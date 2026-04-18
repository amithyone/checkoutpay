@extends('layouts.admin')

@section('title', 'Rental Items')

@section('content')
<div class="p-4">
    <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
        <h1 class="text-lg font-bold text-gray-900">Rental items</h1>
        <div class="flex flex-wrap gap-1.5">
            <a href="{{ route('admin.rentals.index') }}" class="text-xs bg-white border border-gray-300 text-gray-700 px-2.5 py-1.5 rounded-md hover:bg-gray-50">
                <i class="fas fa-list mr-1"></i>Requests
            </a>
            <a href="{{ route('admin.rental-categories.index') }}" class="text-xs bg-white border border-gray-300 text-gray-700 px-2.5 py-1.5 rounded-md hover:bg-gray-50">
                <i class="fas fa-tags mr-1"></i>Categories
            </a>
            <a href="{{ route('admin.rental-items.create') }}" class="text-xs bg-primary text-white px-2.5 py-1.5 rounded-md hover:bg-primary/90">
                <i class="fas fa-plus mr-1"></i>Add item
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-3 mb-3">
        <form method="GET" class="grid grid-cols-2 md:grid-cols-5 gap-2 items-end">
            <div class="col-span-2 md:col-span-1">
                <label class="block text-xs font-medium text-gray-600 mb-0.5">Category</label>
                <select name="category_id" class="w-full text-sm border-gray-300 rounded-md py-1.5">
                    <option value="">All</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-2 md:col-span-1">
                <label class="block text-xs font-medium text-gray-600 mb-0.5">Business</label>
                <select name="business_id" class="w-full text-sm border-gray-300 rounded-md py-1.5">
                    <option value="">All</option>
                    @foreach($businesses as $business)
                        <option value="{{ $business->id }}" {{ request('business_id') == $business->id ? 'selected' : '' }}>
                            {{ $business->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-0.5">Status</label>
                <select name="status" class="w-full text-sm border-gray-300 rounded-md py-1.5">
                    <option value="">All</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="unavailable" {{ request('status') == 'unavailable' ? 'selected' : '' }}>Unavailable</option>
                </select>
            </div>
            <div class="col-span-2 md:col-span-1">
                <button type="submit" class="w-full text-sm bg-primary text-white px-2 py-1.5 rounded-md">Filter</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-3 mb-3">
        <h2 class="text-sm font-semibold text-gray-800 mb-2">Clone catalog between businesses</h2>
        <form method="POST" action="{{ route('admin.rental-items.clone-catalog') }}" class="grid grid-cols-1 md:grid-cols-3 gap-2">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-0.5">Source</label>
                <select name="source_business_id" required class="w-full text-sm border-gray-300 rounded-md py-1.5">
                    <option value="">Select…</option>
                    @foreach($businesses as $business)
                        <option value="{{ $business->id }}">{{ $business->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-0.5">Target</label>
                <select name="target_business_id" required class="w-full text-sm border-gray-300 rounded-md py-1.5">
                    <option value="">Select…</option>
                    @foreach($businesses as $business)
                        <option value="{{ $business->id }}">{{ $business->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full text-sm bg-indigo-600 text-white px-2 py-1.5 rounded-md hover:bg-indigo-700">
                    <i class="fas fa-copy mr-1"></i>Clone catalog
                </button>
            </div>
        </form>
        <p class="text-xs text-gray-500 mt-1.5">Copies all items and images from source to target.</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-2 py-2 w-12"></th>
                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">Business</th>
                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase hidden md:table-cell">Cat</th>
                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">City</th>
                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase">Daily</th>
                    <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">Qty</th>
                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase w-40">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($items as $item)
                    @php
                        $imgs = $item->images;
                        $thumb = is_array($imgs) && count($imgs) ? $imgs[0] : null;
                    @endphp
                    <tr class="hover:bg-gray-50/80">
                        <td class="px-2 py-1.5 align-middle">
                            @if($thumb)
                                <img src="{{ asset('storage/' . $thumb) }}" alt="" class="w-9 h-9 object-cover rounded border border-gray-200">
                            @else
                                <div class="w-9 h-9 bg-gray-100 rounded border border-gray-200"></div>
                            @endif
                        </td>
                        <td class="px-2 py-1.5 align-middle max-w-[140px] md:max-w-[200px]">
                            <span class="font-medium text-gray-900 truncate block" title="{{ $item->name }}">{{ $item->name }}</span>
                        </td>
                        <td class="px-2 py-1.5 align-middle hidden lg:table-cell text-xs">
                            <a href="{{ route('admin.businesses.show', $item->business_id) }}" class="text-primary hover:underline truncate block max-w-[160px]" title="{{ $item->business->name }}">
                                {{ $item->business->name }}
                            </a>
                        </td>
                        <td class="px-2 py-1.5 align-middle hidden md:table-cell text-xs text-gray-600">
                            {{ $item->category->name }}
                        </td>
                        <td class="px-2 py-1.5 align-middle text-xs text-gray-600 whitespace-nowrap">
                            {{ $item->city ?? '—' }}
                        </td>
                        <td class="px-2 py-1.5 align-middle text-right text-xs font-semibold whitespace-nowrap">
                            ₦{{ number_format($item->daily_rate, 0) }}
                        </td>
                        <td class="px-2 py-1.5 align-middle text-center text-xs">{{ $item->quantity_available }}</td>
                        <td class="px-2 py-1.5 align-middle whitespace-nowrap">
                            @if($item->is_active && $item->is_available)
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-800">Active</span>
                            @elseif(!$item->is_active)
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-700">Off</span>
                            @else
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-yellow-800">NA</span>
                            @endif
                        </td>
                        <td class="px-2 py-1.5 align-middle text-right whitespace-nowrap">
                            <a href="{{ route('admin.rental-items.edit', $item) }}" class="text-xs text-primary hover:underline mr-2">Edit</a>
                            <a href="{{ route('admin.rental-items.show', $item) }}" class="text-xs text-gray-600 hover:underline mr-2">View</a>
                            <form action="{{ route('admin.rental-items.destroy', $item) }}" method="POST" class="inline" onsubmit="return confirm('Delete this rental item? This cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:text-red-800 hover:underline font-medium">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-6 text-center text-sm text-gray-500">
                            No rental items. <a href="{{ route('admin.rental-items.create') }}" class="text-primary hover:underline">Create one</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3 text-sm">
        {{ $items->links() }}
    </div>
</div>
@endsection

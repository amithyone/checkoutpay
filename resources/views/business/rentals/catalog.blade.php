@extends('layouts.business')

@section('title', 'Clone Rental Items')

@section('content')
<div class="p-6">
    <a href="{{ route('business.rentals.items') }}" class="text-primary hover:underline mb-4 inline-block">
        <i class="fas fa-arrow-left"></i> Back to My Items
    </a>

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Clone Items</h1>
        <a href="{{ route('business.rentals.items.create') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90">
            <i class="fas fa-plus mr-2"></i> Add Item (Manual)
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Search</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name, city, state..." class="w-full border-gray-300 rounded-md">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Category</label>
                <select name="category_id" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ (string) request('category_id') === (string) $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
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
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Daily Rate</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($items as $item)
                    <tr>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                @php $img = is_array($item->images) && count($item->images) ? $item->images[0] : null; @endphp
                                @if($img)
                                    <img src="{{ asset('storage/' . $img) }}" class="w-10 h-10 object-cover rounded border border-gray-200" alt="">
                                @else
                                    <div class="w-10 h-10 rounded border border-gray-200 bg-gray-50 flex items-center justify-center text-gray-400 text-xs">No</div>
                                @endif
                                <div>
                                    <div class="font-medium">{{ $item->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $item->city ?? '—' }}{{ $item->state ? ', ' . $item->state : '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                            {{ $item->category?->name ?? '—' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                            {{ $item->business?->name ?? '—' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold">
                            ₦{{ number_format($item->daily_rate, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="{{ route('business.rentals.items.clone', $item) }}" class="text-primary hover:underline">
                                <i class="fas fa-copy mr-1"></i> Clone
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-6 text-center text-gray-500">
                            No items found.
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


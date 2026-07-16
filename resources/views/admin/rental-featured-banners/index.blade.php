@extends('layouts.admin')

@section('title', 'Featured banners')

@section('content')
<div class="p-4">
    <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
        <div>
            <h1 class="text-lg font-bold text-gray-900">Featured slider banners</h1>
            <p class="text-xs text-gray-500 mt-0.5">Ads and promos mixed with featured items on the rentals app home carousel.</p>
        </div>
        <div class="flex flex-wrap gap-1.5">
            <a href="{{ route('admin.rentals.index') }}" class="text-xs bg-white border border-gray-300 text-gray-700 px-2.5 py-1.5 rounded-md hover:bg-gray-50">
                <i class="fas fa-list mr-1"></i>Rentals
            </a>
            <a href="{{ route('admin.rental-items.index') }}" class="text-xs bg-white border border-gray-300 text-gray-700 px-2.5 py-1.5 rounded-md hover:bg-gray-50">
                <i class="fas fa-box mr-1"></i>Items
            </a>
            <a href="{{ route('admin.rental-featured-banners.create') }}" class="text-xs bg-primary text-white px-2.5 py-1.5 rounded-md hover:bg-primary/90">
                <i class="fas fa-plus mr-1"></i>Add banner
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-3 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-2 py-2 w-16"></th>
                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase hidden md:table-cell">Tag</th>
                    <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">Sort</th>
                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">Schedule</th>
                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase w-28">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($banners as $banner)
                    <tr class="hover:bg-gray-50/80">
                        <td class="px-2 py-1.5 align-middle">
                            @if($banner->image)
                                <img src="{{ asset('storage/' . $banner->image) }}" alt="" class="w-14 h-10 object-cover rounded border border-gray-200">
                            @endif
                        </td>
                        <td class="px-2 py-1.5 align-middle">
                            <div class="font-medium text-gray-900">{{ $banner->title }}</div>
                            @if($banner->subtitle)
                                <div class="text-xs text-gray-500 truncate max-w-[220px]">{{ $banner->subtitle }}</div>
                            @endif
                            @if($banner->rentalItem)
                                <div class="text-[10px] text-primary">Links to: {{ $banner->rentalItem->name }}</div>
                            @elseif($banner->link_url)
                                <div class="text-[10px] text-gray-500 truncate max-w-[220px]">{{ $banner->link_url }}</div>
                            @endif
                        </td>
                        <td class="px-2 py-1.5 align-middle hidden md:table-cell text-xs">{{ $banner->tag }}</td>
                        <td class="px-2 py-1.5 align-middle text-center text-xs font-semibold">{{ $banner->sort_order }}</td>
                        <td class="px-2 py-1.5 align-middle whitespace-nowrap">
                            @if($banner->isLiveNow())
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-800">Live</span>
                            @elseif($banner->is_active)
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-yellow-800">Scheduled</span>
                            @else
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-700">Off</span>
                            @endif
                        </td>
                        <td class="px-2 py-1.5 align-middle hidden lg:table-cell text-[10px] text-gray-500">
                            @if($banner->starts_at || $banner->ends_at)
                                {{ optional($banner->starts_at)?->format('M j') ?: '…' }}
                                –
                                {{ optional($banner->ends_at)?->format('M j, Y') ?: '…' }}
                            @else
                                Always
                            @endif
                        </td>
                        <td class="px-2 py-1.5 align-middle text-right whitespace-nowrap">
                            <a href="{{ route('admin.rental-featured-banners.edit', $banner) }}" class="text-xs text-primary hover:underline mr-2">Edit</a>
                            <form action="{{ route('admin.rental-featured-banners.destroy', $banner) }}" method="POST" class="inline" onsubmit="return confirm('Delete this banner?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-8 text-center text-sm text-gray-500">
                            No featured banners yet.
                            <a href="{{ route('admin.rental-featured-banners.create') }}" class="text-primary hover:underline">Add your first ad</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $banners->links() }}</div>
</div>
@endsection

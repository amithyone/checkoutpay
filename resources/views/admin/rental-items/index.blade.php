@extends('layouts.admin')

@section('title', 'Rental Items')

@section('content')
@include('admin.rental-items.partials.form-ui-styles')
<div class="p-4 rental-admin-form" id="rental-items-admin">
    <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
        <h1 class="text-lg font-bold text-gray-900">Rental items</h1>
        <div class="flex flex-wrap gap-1.5">
            <a href="{{ route('admin.rentals.index') }}" class="text-xs bg-white border border-gray-300 text-gray-700 px-2.5 py-1.5 rounded-md hover:bg-gray-50">
                <i class="fas fa-list mr-1"></i>Requests
            </a>
            <a href="{{ route('admin.rental-categories.index') }}" class="text-xs bg-white border border-gray-300 text-gray-700 px-2.5 py-1.5 rounded-md hover:bg-gray-50">
                <i class="fas fa-tags mr-1"></i>Categories
            </a>
            <a href="{{ route('admin.rental-featured-banners.index') }}" class="text-xs bg-white border border-gray-300 text-gray-700 px-2.5 py-1.5 rounded-md hover:bg-gray-50">
                <i class="fas fa-images mr-1"></i>Featured banners
            </a>
            <a href="{{ route('admin.rental-items.create') }}" class="text-xs bg-primary text-white px-2.5 py-1.5 rounded-md hover:bg-primary/90">
                <i class="fas fa-plus mr-1"></i>Add item
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-3 text-sm bg-green-50 border border-green-200 text-green-800 px-3 py-2 rounded-md">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-3 text-sm bg-red-50 border border-red-200 text-red-800 px-3 py-2 rounded-md">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-3 mb-3">
        <form method="GET" class="grid grid-cols-2 md:grid-cols-6 gap-2 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-0.5">Category</label>
                <select name="category_id" class="w-full text-sm border-gray-300 rounded-md py-1.5">
                    <option value="">All</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-0.5">Business</label>
                <select name="business_id" class="w-full text-sm border-gray-300 rounded-md py-1.5">
                    <option value="">All</option>
                    @foreach($businesses as $business)
                        <option value="{{ $business->id }}" {{ request('business_id') == $business->id ? 'selected' : '' }}>{{ $business->name }}</option>
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
                    <option value="featured" {{ request('status') == 'featured' ? 'selected' : '' }}>Featured slider</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-0.5">How-to videos</label>
                <select name="how_to_filter" class="w-full text-sm border-gray-300 rounded-md py-1.5">
                    <option value="">Any</option>
                    <option value="with" {{ request('how_to_filter') == 'with' ? 'selected' : '' }}>Has videos</option>
                    <option value="without" {{ request('how_to_filter') == 'without' ? 'selected' : '' }}>Missing videos</option>
                </select>
            </div>
            <div class="col-span-2 md:col-span-1">
                <label class="block text-xs font-medium text-gray-600 mb-0.5">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, business…" class="w-full text-sm border-gray-300 rounded-md py-1.5">
            </div>
            <div>
                <button type="submit" class="rental-admin-save-btn md:w-auto md:max-w-xs">
                    Filter items
                </button>
            </div>
        </form>
        <p class="text-xs text-gray-500 mt-2">{{ number_format($filteredTotal) }} item(s) match these filters.</p>
    </div>

    <form method="POST" action="{{ route('admin.rental-items.bulk-how-to-videos') }}" id="bulk-how-to-form">
        @csrf
        <input type="hidden" name="select_all_filtered" id="select_all_filtered" value="0">
        @foreach(['category_id', 'business_id', 'status', 'how_to_filter', 'search'] as $filterKey)
            @if(request()->filled($filterKey))
                <input type="hidden" name="{{ $filterKey }}" value="{{ request($filterKey) }}">
            @endif
        @endforeach

        <div class="bg-white rounded-lg shadow-sm border border-sky-100 p-3 mb-3">
            <div class="flex flex-wrap justify-between items-center gap-2 mb-2">
                <div class="text-sm font-semibold text-sky-900">
                    <i class="fab fa-youtube mr-1"></i>Bulk how-to videos
                </div>
                <div class="text-xs text-gray-600">
                    <span id="selected-count">0</span> selected
                    @if($filteredTotal > 0)
                        · <button type="button" id="select-all-filtered" class="text-primary hover:underline font-medium">Select all {{ number_format($filteredTotal) }} matching filters</button>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-2 mb-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-0.5">Action</label>
                    <select name="mode" id="bulk-mode">
                        <option value="replace">Replace videos on selected items</option>
                        <option value="append">Append videos (keep existing)</option>
                        <option value="clear">Clear all videos on selected items</option>
                    </select>
                </div>
                <div class="md:col-span-3 flex items-end">
                    <button type="submit" class="rental-admin-save-btn md:w-auto" onclick="return confirmBulkHowTo();">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        Save how-to videos to selected
                    </button>
                </div>
            </div>

            <div id="bulk-video-fields">
                @include('admin.rental-items.partials.how-to-videos-fields', ['howToVideos' => [['title' => '', 'url' => '']], 'fieldPrefix' => 'how_to_videos'])
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-2 py-2 w-8">
                            <input type="checkbox" id="select-page" class="rounded border-gray-300" title="Select all on this page">
                        </th>
                        <th class="px-2 py-2 w-12"></th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">Business</th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase hidden md:table-cell">Cat</th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">Videos</th>
                        <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase">Daily</th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase w-32">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($items as $item)
                        @php
                            $imgs = $item->images;
                            $thumb = is_array($imgs) && count($imgs) ? $imgs[0] : null;
                            $videoCount = count(\App\Models\RentalItem::normalizeHowToVideos($item->how_to_videos ?? []));
                        @endphp
                        <tr class="hover:bg-gray-50/80 item-row">
                            <td class="px-2 py-1.5 align-middle">
                                <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="item-checkbox rounded border-gray-300">
                            </td>
                            <td class="px-2 py-1.5 align-middle">
                                @if($thumb)
                                    <img src="{{ asset('storage/' . $thumb) }}" alt="" class="w-9 h-9 object-cover rounded border border-gray-200">
                                @else
                                    <div class="w-9 h-9 bg-gray-100 rounded border border-gray-200"></div>
                                @endif
                            </td>
                            <td class="px-2 py-1.5 align-middle max-w-[160px]">
                                <span class="font-medium text-gray-900 truncate block" title="{{ $item->name }}">{{ $item->name }}</span>
                            </td>
                            <td class="px-2 py-1.5 align-middle hidden lg:table-cell text-xs">
                                <span class="truncate block max-w-[140px]" title="{{ $item->business->name }}">{{ $item->business->name }}</span>
                            </td>
                            <td class="px-2 py-1.5 align-middle hidden md:table-cell text-xs text-gray-600">{{ $item->category->name }}</td>
                            <td class="px-2 py-1.5 align-middle text-center">
                                @if($videoCount > 0)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-sky-100 text-sky-800">{{ $videoCount }}</span>
                                @else
                                    <span class="text-[10px] text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-2 py-1.5 align-middle text-right text-xs font-semibold whitespace-nowrap">₦{{ number_format($item->daily_rate, 0) }}</td>
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
                                <a href="{{ route('admin.rental-items.edit', $item) }}" class="text-xs text-primary hover:underline">Edit</a>
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
    </form>

    <div class="mt-3 text-sm">{{ $items->links() }}</div>
</div>

@push('scripts')
<script>
(function () {
    const form = document.getElementById('bulk-how-to-form');
    const selectPage = document.getElementById('select-page');
    const selectAllFiltered = document.getElementById('select-all-filtered');
    const selectAllFilteredInput = document.getElementById('select_all_filtered');
    const selectedCountEl = document.getElementById('selected-count');
    const bulkMode = document.getElementById('bulk-mode');
    const bulkVideoFields = document.getElementById('bulk-video-fields');
    const filteredTotal = {{ (int) $filteredTotal }};

    function itemCheckboxes() {
        return Array.from(document.querySelectorAll('.item-checkbox'));
    }

    function updateSelectedCount() {
        if (selectAllFilteredInput.value === '1') {
            selectedCountEl.textContent = filteredTotal.toLocaleString();
            return;
        }
        selectedCountEl.textContent = itemCheckboxes().filter(function (cb) { return cb.checked; }).length;
    }

    if (selectPage) {
        selectPage.addEventListener('change', function () {
            selectAllFilteredInput.value = '0';
            itemCheckboxes().forEach(function (cb) { cb.checked = selectPage.checked; });
            updateSelectedCount();
        });
    }

    itemCheckboxes().forEach(function (cb) {
        cb.addEventListener('change', function () {
            selectAllFilteredInput.value = '0';
            if (selectPage) {
                selectPage.checked = itemCheckboxes().every(function (x) { return x.checked; });
            }
            updateSelectedCount();
        });
    });

    if (selectAllFiltered) {
        selectAllFiltered.addEventListener('click', function () {
            selectAllFilteredInput.value = '1';
            itemCheckboxes().forEach(function (cb) { cb.checked = true; });
            if (selectPage) selectPage.checked = true;
            updateSelectedCount();
        });
    }

    if (bulkMode && bulkVideoFields) {
        bulkMode.addEventListener('change', function () {
            bulkVideoFields.style.display = bulkMode.value === 'clear' ? 'none' : '';
        });
    }

    window.confirmBulkHowTo = function () {
        const mode = bulkMode ? bulkMode.value : 'replace';
        const count = selectAllFilteredInput.value === '1'
            ? filteredTotal
            : itemCheckboxes().filter(function (cb) { return cb.checked; }).length;

        if (count < 1) {
            alert('Select at least one item, or use “Select all matching filters”.');
            return false;
        }

        const verb = mode === 'clear' ? 'clear how-to videos on' : (mode === 'append' ? 'append how-to videos to' : 'set how-to videos on');
        return confirm('This will ' + verb + ' ' + count + ' item(s). Continue?');
    };

    updateSelectedCount();
})();
</script>
@endpush
@endsection

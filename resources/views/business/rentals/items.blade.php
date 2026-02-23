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
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Photo</th>
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
                    <tr data-item-id="{{ $item->id }}">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-medium">{{ $item->name }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2 flex-wrap item-photos-container">
                                @if($item->images && count($item->images) > 0)
                                    @foreach(array_slice($item->images, 0, 3) as $img)
                                        <img src="{{ asset('storage/' . $img) }}" alt="" class="w-10 h-10 object-cover rounded border border-gray-200">
                                    @endforeach
                                @else
                                    <span class="no-photo-placeholder text-gray-400 text-xs">No photo</span>
                                @endif
                                <label class="cursor-pointer text-primary hover:underline text-xs whitespace-nowrap">
                                    <input type="file" accept="image/*" class="hidden item-photo-input" data-item-id="{{ $item->id }}" data-url="{{ route('business.rentals.items.add-photo', $item) }}">
                                    <i class="fas fa-plus mr-1"></i> Upload
                                </label>
                            </div>
                            <div class="item-photo-feedback mt-1 text-xs hidden text-green-600"></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm">{{ $item->category->name }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm">{{ $item->city ?? 'N/A' }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="daily-rate-cell inline-flex items-center gap-1" data-item-id="{{ $item->id }}" data-url="{{ route('business.rentals.items.update-daily-rate', $item) }}">
                                <span class="daily-rate-display font-semibold cursor-pointer hover:text-primary" title="Click to edit">₦{{ number_format($item->daily_rate, 2) }}</span>
                                <input type="number" step="0.01" min="0" class="daily-rate-input hidden w-24 border border-gray-300 rounded px-2 py-1 text-sm" value="{{ $item->daily_rate }}">
                                <span class="daily-rate-saving hidden text-xs text-gray-500">Saving...</span>
                            </div>
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
                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                            No rental items found. <a href="{{ route('business.rentals.items.create') }}" class="text-primary hover:underline">Create one</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var csrf = document.querySelector('meta[name="csrf-token"]');
        var token = csrf ? csrf.getAttribute('content') : '';

        // Inline daily rate edit
        document.querySelectorAll('.daily-rate-cell').forEach(function(cell) {
            var display = cell.querySelector('.daily-rate-display');
            var input = cell.querySelector('.daily-rate-input');
            var saving = cell.querySelector('.daily-rate-saving');
            var url = cell.getAttribute('data-url');
            var itemId = cell.getAttribute('data-item-id');

            display.addEventListener('click', function() {
                display.classList.add('hidden');
                input.classList.remove('hidden');
                input.focus();
                input.select();
            });

            function saveRate() {
                var val = parseFloat(input.value);
                if (isNaN(val) || val < 0) {
                    input.classList.add('hidden');
                    display.classList.remove('hidden');
                    return;
                }
                saving.classList.remove('hidden');
                input.classList.add('hidden');
                fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ daily_rate: val })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    saving.classList.add('hidden');
                    display.textContent = data.formatted || ('₦' + Number(data.daily_rate).toLocaleString('en-NG', { minimumFractionDigits: 2 }));
                    display.classList.remove('hidden');
                })
                .catch(function() {
                    saving.classList.add('hidden');
                    display.classList.remove('hidden');
                    input.classList.remove('hidden');
                    alert('Failed to update rate.');
                });
            }

            input.addEventListener('blur', saveRate);
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); saveRate(); }
                if (e.key === 'Escape') {
                    input.value = input.getAttribute('value');
                    input.classList.add('hidden');
                    display.classList.remove('hidden');
                }
            });
        });

        // Quick photo upload
        document.querySelectorAll('.item-photo-input').forEach(function(inp) {
            inp.addEventListener('change', function() {
                var file = this.files[0];
                if (!file) return;
                var url = this.getAttribute('data-url');
                var itemId = this.getAttribute('data-item-id');
                var row = document.querySelector('tr[data-item-id="' + itemId + '"]');
                var feedback = row ? row.querySelector('.item-photo-feedback') : null;
                var container = row ? row.querySelector('.item-photos-container') : null;

                if (feedback) feedback.classList.remove('hidden'), feedback.textContent = 'Uploading...';

                var formData = new FormData();
                formData.append('photo', file);
                formData.append('_token', token);

                fetch(url, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: formData
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && container) {
                        var placeholder = container.querySelector('.no-photo-placeholder');
                        if (placeholder) placeholder.classList.add('hidden');
                        var img = document.createElement('img');
                        img.src = data.image_url;
                        img.alt = '';
                        img.className = 'w-10 h-10 object-cover rounded border border-gray-200';
                        container.insertBefore(img, container.firstChild);
                    }
                    if (feedback) feedback.textContent = 'Photo added.', feedback.classList.remove('hidden');
                    inp.value = '';
                })
                .catch(function() {
                    if (feedback) feedback.textContent = 'Upload failed.', feedback.classList.add('text-red-600');
                });
            });
        });
    });
    </script>
    @endpush

    <div class="mt-4">
        {{ $items->links() }}
    </div>
</div>
@endsection

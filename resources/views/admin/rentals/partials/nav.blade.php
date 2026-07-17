{{-- Shared nav for rentals ops pages --}}
@php
    $navClass = function (array $patterns) {
        return request()->routeIs(...$patterns)
            ? 'bg-primary/10 text-primary border-primary/30'
            : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50';
    };
@endphp
<nav class="flex flex-wrap gap-2 mb-2">
    <a href="{{ route('admin.rentals.index') }}"
       class="inline-flex items-center shrink-0 px-3 py-2 rounded-lg border text-sm {{ $navClass(['admin.rentals.*']) }}">
        <i class="fas fa-camera mr-2"></i> Orders
    </a>
    <a href="{{ route('admin.rental-items.index') }}"
       class="inline-flex items-center shrink-0 px-3 py-2 rounded-lg border text-sm {{ $navClass(['admin.rental-items.*']) }}">
        <i class="fas fa-box mr-2"></i> Items
    </a>
    <a href="{{ route('admin.rental-categories.index') }}"
       class="inline-flex items-center shrink-0 px-3 py-2 rounded-lg border text-sm {{ $navClass(['admin.rental-categories.*']) }}">
        <i class="fas fa-tags mr-2"></i> Categories
    </a>
    <a href="{{ route('admin.rental-featured-banners.index') }}"
       class="inline-flex items-center shrink-0 px-3 py-2 rounded-lg border text-sm {{ $navClass(['admin.rental-featured-banners.*']) }}">
        <i class="fas fa-images mr-2"></i> Banners
    </a>
    <a href="{{ route('admin.rentals-app-sessions.index') }}"
       class="inline-flex items-center shrink-0 px-3 py-2 rounded-lg border text-sm {{ $navClass(['admin.rentals-app-sessions.*']) }}">
        <i class="fas fa-mobile-alt mr-2 text-blue-600"></i> App sessions
    </a>
</nav>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#3C50E0' },
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    @include('partials.nav')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Marketplace</h1>
            <p class="text-gray-600">Browse all available memberships and rentals in one place</p>
        </div>

        <!-- Type Tabs -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('marketplace.index', ['type' => 'all'] + request()->except('type')) }}" 
                   class="px-4 py-2 rounded-lg {{ $type === 'all' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    All
                </a>
                <a href="{{ route('marketplace.index', ['type' => 'memberships'] + request()->except('type')) }}" 
                   class="px-4 py-2 rounded-lg {{ $type === 'memberships' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    <i class="fas fa-id-card mr-2"></i> Memberships
                </a>
                <a href="{{ route('marketplace.index', ['type' => 'rentals'] + request()->except('type')) }}" 
                   class="px-4 py-2 rounded-lg {{ $type === 'rentals' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    <i class="fas fa-box mr-2"></i> Rentals
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
            <form method="GET" action="{{ route('marketplace.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="type" value="{{ $type }}">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..." class="w-full border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category" class="w-full border-gray-300 rounded-md">
                        <option value="">All Categories</option>
                        @if($type === 'all' || $type === 'memberships')
                            <optgroup label="Membership Categories">
                                @foreach($membershipCategories as $category)
                                    <option value="{{ $category->slug }}" {{ request('category') == $category->slug ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if($type === 'all' || $type === 'rentals')
                            <optgroup label="Rental Categories">
                                @foreach($rentalCategories as $category)
                                    <option value="{{ $category->slug }}" {{ request('category') == $category->slug ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                    <select name="city" class="w-full border-gray-300 rounded-md">
                        <option value="">All Cities</option>
                        @foreach($cities as $city)
                            <option value="{{ $city }}" {{ request('city') == $city ? 'selected' : '' }}>
                                {{ $city }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-primary text-white px-4 py-2 rounded-md hover:bg-primary/90">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Memberships Section -->
        @if(($type === 'all' || $type === 'memberships') && $memberships->count() > 0)
            <div class="mb-12">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">
                        <i class="fas fa-id-card text-primary mr-2"></i> Memberships
                    </h2>
                    <a href="{{ route('memberships.index') }}" class="text-primary hover:underline text-sm">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($memberships as $membership)
                        <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
                            @if($membership->images && count($membership->images) > 0)
                                <img src="{{ asset('storage/' . $membership->images[0]) }}" alt="{{ $membership->name }}" class="w-full h-48 object-cover rounded-t-lg">
                            @else
                                <div class="w-full h-48 bg-gradient-to-br from-primary/20 to-primary/5 rounded-t-lg flex items-center justify-center">
                                    <i class="fas fa-id-card text-primary text-5xl"></i>
                                </div>
                            @endif
                            <div class="p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <h3 class="font-semibold text-lg">{{ $membership->name }}</h3>
                                    @if($membership->is_featured)
                                        <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">Featured</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 mb-2">
                                    @if($membership->category)
                                        <span class="text-sm text-gray-500">{{ $membership->category->name }}</span>
                                    @endif
                                    @if($membership->is_global)
                                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">Global</span>
                                    @elseif($membership->city)
                                        <span class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">
                                            <i class="fas fa-map-marker-alt mr-1"></i>{{ $membership->city }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-gray-600 text-sm mb-4">{{ \Illuminate\Support\Str::limit($membership->description, 80) }}</p>
                                <div class="flex justify-between items-center mb-4">
                                    <div>
                                        <span class="text-primary font-bold text-xl">₦{{ number_format($membership->price, 2) }}</span>
                                        <span class="text-sm text-gray-500">/ {{ $membership->formatted_duration }}</span>
                                    </div>
                                </div>
                                <a href="{{ route('memberships.show', $membership->slug) }}" class="block w-full bg-primary text-white text-center py-2 rounded-md hover:bg-primary/90">
                                    View Details
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Rentals Section -->
        @if(($type === 'all' || $type === 'rentals') && $rentals->count() > 0)
            <div class="mb-12">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">
                        <i class="fas fa-box text-primary mr-2"></i> Rentals
                    </h2>
                    <a href="{{ route('rentals.index') }}" class="text-primary hover:underline text-sm">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($rentals as $item)
                        <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
                            @if($item->images && count($item->images) > 0)
                                <img src="{{ asset('storage/' . $item->images[0]) }}" alt="{{ $item->name }}" class="w-full h-48 object-cover rounded-t-lg">
                            @else
                                <div class="w-full h-48 bg-gray-200 rounded-t-lg flex items-center justify-center">
                                    <i class="fas fa-image text-gray-400 text-5xl"></i>
                                </div>
                            @endif
                            <div class="p-4">
                                <h3 class="font-semibold text-lg mb-2">{{ $item->name }}</h3>
                                @if($item->category)
                                    <span class="text-sm text-gray-500 mb-2 block">{{ $item->category->name }}</span>
                                @endif
                                <p class="text-gray-600 text-sm mb-4">{{ \Illuminate\Support\Str::limit($item->description, 80) }}</p>
                                <div class="flex justify-between items-center mb-4">
                                    <span class="text-primary font-bold">₦{{ number_format($item->daily_rate, 2) }}/day</span>
                                    <span class="text-sm text-gray-500">{{ $item->city }}</span>
                                </div>
                                <a href="{{ route('rentals.show', $item->slug) }}" class="block w-full bg-primary text-white text-center py-2 rounded-md hover:bg-primary/90">
                                    View Details
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Empty State -->
        @if(($type === 'all' && $memberships->count() === 0 && $rentals->count() === 0) || 
            ($type === 'memberships' && $memberships->count() === 0) || 
            ($type === 'rentals' && $rentals->count() === 0))
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <i class="fas fa-search text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-600 mb-4">No items found matching your criteria.</p>
                <a href="{{ route('marketplace.index') }}" class="text-primary hover:underline">Clear filters</a>
            </div>
        @endif
    </div>

    @include('partials.footer')
</body>
</html>

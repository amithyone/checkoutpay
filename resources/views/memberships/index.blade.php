<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memberships - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    @include('partials.nav')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Memberships</h1>
            <p class="text-gray-600">Find the perfect membership plan for your fitness journey, classes, and more</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" action="{{ route('memberships.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category" class="w-full border-gray-300 rounded-md">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->slug }}" {{ request('category') == $category->slug ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
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
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search memberships..." class="w-full border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                    <select name="sort" class="w-full border-gray-300 rounded-md">
                        <option value="featured" {{ request('sort') == 'featured' ? 'selected' : '' }}>Featured</option>
                        <option value="price_low" {{ request('sort') == 'price_low' ? 'selected' : '' }}>Price: Low to High</option>
                        <option value="price_high" {{ request('sort') == 'price_high' ? 'selected' : '' }}>Price: High to Low</option>
                        <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>Newest</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-primary text-white px-4 py-2 rounded-md hover:bg-primary/90">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Memberships Grid -->
        @if($memberships->count() > 0)
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
                        <div class="p-6">
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
                            <p class="text-gray-600 text-sm mb-4">{{ \Illuminate\Support\Str::limit($membership->description, 100) }}</p>
                            
                            <!-- Who is it for -->
                            @if($membership->who_is_it_for)
                                <div class="mb-4">
                                    <p class="text-xs font-semibold text-gray-700 mb-1">Who is it for:</p>
                                    <p class="text-sm text-gray-600">{{ \Illuminate\Support\Str::limit($membership->who_is_it_for, 80) }}</p>
                                </div>
                            @endif

                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <span class="text-primary font-bold text-xl">₦{{ number_format($membership->price, 2) }}</span>
                                    <span class="text-sm text-gray-500">/ {{ $membership->formatted_duration }}</span>
                                </div>
                                @if($membership->max_members)
                                    <span class="text-xs text-gray-500">{{ $membership->current_members }}/{{ $membership->max_members }} members</span>
                                @endif
                            </div>

                            @if($membership->features && count($membership->features) > 0)
                                <ul class="text-xs text-gray-600 mb-4 space-y-1">
                                    @foreach(array_slice($membership->features, 0, 3) as $feature)
                                        <li><i class="fas fa-check text-green-500 mr-1"></i> {{ $feature }}</li>
                                    @endforeach
                                    @if(count($membership->features) > 3)
                                        <li class="text-gray-400">+{{ count($membership->features) - 3 }} more</li>
                                    @endif
                                </ul>
                            @endif

                            <a href="{{ route('memberships.show', $membership->slug) }}" class="block w-full bg-primary text-white text-center py-2 rounded-md hover:bg-primary/90">
                                View Details
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $memberships->links() }}
            </div>
        @else
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <i class="fas fa-search text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-600">No memberships found.</p>
            </div>
        @endif
    </div>

    @include('partials.footer')
</body>
</html>

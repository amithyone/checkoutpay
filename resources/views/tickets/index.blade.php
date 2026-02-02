<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Events & Tickets - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
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
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Browse Events & Tickets</h1>
            <p class="text-gray-600">Discover and purchase tickets for upcoming events</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
            <form method="GET" action="{{ route('tickets.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search events..." class="w-full border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <select name="date" class="w-full border-gray-300 rounded-md">
                        <option value="">All Events</option>
                        <option value="upcoming" {{ request('date') == 'upcoming' ? 'selected' : '' }}>Upcoming</option>
                        <option value="past" {{ request('date') == 'past' ? 'selected' : '' }}>Past Events</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort</label>
                    <select name="sort" class="w-full border-gray-300 rounded-md">
                        <option value="upcoming" {{ request('sort') == 'upcoming' ? 'selected' : '' }}>Upcoming First</option>
                        <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>Newest First</option>
                        <option value="oldest" {{ request('sort') == 'oldest' ? 'selected' : '' }}>Oldest First</option>
                        <option value="price_low" {{ request('sort') == 'price_low' ? 'selected' : '' }}>Price: Low to High</option>
                        <option value="price_high" {{ request('sort') == 'price_high' ? 'selected' : '' }}>Price: High to Low</option>
                    </select>
                </div>
                <div class="flex items-end md:col-span-3">
                    <button type="submit" class="w-full md:w-auto bg-primary text-white px-4 py-2 rounded-md hover:bg-primary/90">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Events Grid -->
        @if($events->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($events as $event)
                    <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
                        @if($event->image)
                            <img src="{{ asset('storage/' . $event->image) }}" alt="{{ $event->title }}" class="w-full h-48 object-cover rounded-t-lg">
                        @else
                            <div class="w-full h-48 bg-gradient-to-br from-primary/20 to-primary/5 rounded-t-lg flex items-center justify-center">
                                <i class="fas fa-ticket-alt text-primary text-5xl"></i>
                            </div>
                        @endif
                        <div class="p-4">
                            <h3 class="font-semibold text-lg mb-2">{{ $event->title }}</h3>
                            <div class="space-y-2 text-sm text-gray-600 mb-4">
                                @if($event->start_date)
                                    <div class="flex items-center">
                                        <i class="fas fa-calendar-alt mr-2 text-primary"></i>
                                        {{ $event->start_date->format('M d, Y') }}
                                        @if($event->start_date)
                                            {{ $event->start_date->format('h:i A') }}
                                        @endif
                                    </div>
                                @endif
                                @if($event->venue)
                                    <div class="flex items-center">
                                        <i class="fas fa-map-marker-alt mr-2 text-primary"></i>
                                        {{ $event->venue }}
                                    </div>
                                @endif
                                @if($event->business)
                                    <div class="flex items-center">
                                        <i class="fas fa-building mr-2 text-primary"></i>
                                        {{ $event->business->name }}
                                    </div>
                                @endif
                            </div>
                            @if($event->ticketTypes && $event->ticketTypes->count() > 0)
                                <div class="mb-4">
                                    <p class="text-xs text-gray-500 mb-1">Starting from:</p>
                                    <p class="text-primary font-bold text-lg">
                                        ₦{{ number_format($event->ticketTypes->min('price'), 2) }}
                                    </p>
                                </div>
                            @endif
                            <a href="{{ route('tickets.show', $event->id) }}" class="block w-full bg-primary text-white text-center py-2 rounded-md hover:bg-primary/90">
                                View Event & Buy Tickets
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $events->links() }}
            </div>
        @else
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <i class="fas fa-calendar-times text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-600 mb-4">No events found matching your criteria.</p>
                <a href="{{ route('tickets.index') }}" class="text-primary hover:underline">Clear filters</a>
            </div>
        @endif
    </div>

    @include('partials.footer')
</body>
</html>

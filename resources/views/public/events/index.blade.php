<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - CheckoutPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 py-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold text-gray-900">Upcoming Events</h1>
                    <div class="flex gap-2">
                        <input type="text" id="search-input" placeholder="Search events..." class="px-4 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                </div>
            </div>
        </header>

        <!-- Events Grid -->
        <main class="max-w-7xl mx-auto px-4 py-8">
            @if($events->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($events as $event)
                        <a href="{{ route('public.events.show', $event) }}" class="bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow overflow-hidden block">
                            @if($event->event_image)
                                <img src="{{ asset('storage/' . $event->event_image) }}" alt="{{ $event->title }}" class="w-full h-48 object-cover">
                            @else
                                <div class="w-full h-48 bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center">
                                    <i class="fas fa-calendar-alt text-white text-4xl"></i>
                                </div>
                            @endif
                            
                            <div class="p-5">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">{{ $event->title }}</h3>
                                <p class="text-sm text-gray-600 mb-4 line-clamp-2">{{ $event->short_description ?? Str::limit($event->description, 100) }}</p>
                                
                                <div class="space-y-2 mb-4">
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-calendar-alt mr-2 text-blue-600"></i>
                                        <span>{{ $event->start_date->format('M d, Y h:i A') }}</span>
                                    </div>
                                    @if($event->venue_name)
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>
                                            <span>{{ $event->venue_name }}</span>
                                        </div>
                                    @endif
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-blue-600">View Details</span>
                                    <i class="fas fa-arrow-right text-blue-600"></i>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-8">
                    {{ $events->links() }}
                </div>
            @else
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                    <i class="fas fa-calendar-times text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No events found</h3>
                    <p class="text-gray-600">Check back later for upcoming events</p>
                </div>
            @endif
        </main>
    </div>
</body>
</html>

@extends('layouts.business')

@section('title', 'Events')
@section('page-title', 'My Events')

@section('content')
<div class="space-y-4 lg:space-y-6 pb-20 lg:pb-0">
    <!-- Header Actions -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Events</h1>
            <p class="text-sm text-gray-600 mt-1">Manage your events and ticket sales</p>
        </div>
        <a href="{{ route('business.events.create') }}" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 flex items-center gap-2">
            <i class="fas fa-plus"></i>
            <span>Create Event</span>
        </a>
    </div>

    <!-- Events List -->
    @if($events->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
            @foreach($events as $event)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow overflow-hidden">
                    @if($event->event_image)
                        <img src="{{ asset('storage/' . $event->event_image) }}" alt="{{ $event->title }}" class="w-full h-48 object-cover">
                    @else
                        <div class="w-full h-48 bg-gradient-to-br from-primary/20 to-primary/5 flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-primary text-4xl"></i>
                        </div>
                    @endif
                    
                    <div class="p-5">
                        <div class="flex items-start justify-between mb-2">
                            <h3 class="text-lg font-semibold text-gray-900 line-clamp-2">{{ $event->title }}</h3>
                            <span class="px-2 py-1 text-xs rounded-full 
                                {{ $event->status === 'published' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $event->status === 'draft' ? 'bg-gray-100 text-gray-800' : '' }}
                                {{ $event->status === 'cancelled' ? 'bg-red-100 text-red-800' : '' }}">
                                {{ ucfirst($event->status) }}
                            </span>
                        </div>
                        
                        <p class="text-sm text-gray-600 mb-4 line-clamp-2">{{ $event->short_description ?? Str::limit($event->description, 100) }}</p>
                        
                        <div class="space-y-2 mb-4">
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-calendar-alt mr-2 text-primary"></i>
                                <span>{{ $event->start_date->format('M d, Y h:i A') }}</span>
                            </div>
                            @if($event->venue_name)
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-map-marker-alt mr-2 text-primary"></i>
                                    <span>{{ $event->venue_name }}</span>
                                </div>
                            @endif
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-ticket-alt mr-2 text-primary"></i>
                                <span>{{ $event->current_attendees }} / {{ $event->max_attendees ?? '∞' }} sold</span>
                            </div>
                        </div>
                        
                        <div class="flex gap-2">
                            <a href="{{ route('business.events.show', $event) }}" class="flex-1 px-3 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-center text-sm">
                                View
                            </a>
                            <a href="{{ route('business.events.edit', $event) }}" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                                <i class="fas fa-edit"></i>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $events->links() }}
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
            <i class="fas fa-calendar-times text-4xl text-gray-400 mb-4"></i>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No events yet</h3>
            <p class="text-gray-600 mb-6">Create your first event to start selling tickets</p>
            <a href="{{ route('business.events.create') }}" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                <i class="fas fa-plus mr-2"></i>
                Create Event
            </a>
        </div>
    @endif
</div>
@endsection

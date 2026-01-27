@extends('layouts.business')

@section('title', 'Events')
@section('page-title', 'Events')

@section('content')
<div class="space-y-4 lg:space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">My Events</h1>
        <a href="{{ route('business.tickets.events.create') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 flex items-center">
            <i class="fas fa-plus mr-2"></i> Create Event
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if($events->count() > 0)
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($events as $event)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900">{{ $event->title }}</div>
                                <div class="text-sm text-gray-500">{{ $event->venue }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $event->start_date->format('M d, Y') }}<br>
                                <span class="text-xs">{{ $event->start_date->format('h:i A') }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    @if($event->status === 'published') bg-green-100 text-green-800
                                    @elseif($event->status === 'draft') bg-gray-100 text-gray-800
                                    @elseif($event->status === 'cancelled') bg-red-100 text-red-800
                                    @else bg-blue-100 text-blue-800
                                    @endif">
                                    {{ ucfirst($event->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $event->total_orders ?? 0 }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                ₦{{ number_format($event->total_revenue ?? 0, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ route('business.tickets.events.show', $event) }}" class="text-primary hover:text-primary/80 mr-3">View</a>
                                @if($event->status === 'draft')
                                    <form action="{{ route('business.tickets.events.publish', $event) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-green-600 hover:text-green-800">Publish</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $events->links() }}
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm p-12 text-center">
            <i class="fas fa-calendar-times text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No events yet</h3>
            <p class="text-gray-500 mb-6">Create your first event to start selling tickets</p>
            <a href="{{ route('business.tickets.events.create') }}" class="bg-primary text-white px-6 py-3 rounded-lg hover:bg-primary/90 inline-flex items-center">
                <i class="fas fa-plus mr-2"></i> Create Event
            </a>
        </div>
    @endif
</div>
@endsection

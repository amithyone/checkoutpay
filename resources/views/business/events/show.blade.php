@extends('layouts.business')

@section('title', $event->title)
@section('page-title', $event->title)

@section('content')
<div class="space-y-6 pb-20 lg:pb-0">
    <!-- Event Header -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $event->title }}</h1>
                    <span class="px-3 py-1 text-xs rounded-full 
                        {{ $event->status === 'published' ? 'bg-green-100 text-green-800' : '' }}
                        {{ $event->status === 'draft' ? 'bg-gray-100 text-gray-800' : '' }}
                        {{ $event->status === 'cancelled' ? 'bg-red-100 text-red-800' : '' }}">
                        {{ ucfirst($event->status) }}
                    </span>
                </div>
                <p class="text-gray-600">{{ $event->short_description ?? Str::limit($event->description, 150) }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('business.events.edit', $event) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    <i class="fas fa-edit mr-2"></i>Edit
                </a>
                @if($event->status === 'draft')
                    <form action="{{ route('business.events.publish', $event) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                            <i class="fas fa-eye mr-2"></i>Publish
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if($event->event_image)
            <img src="{{ asset('storage/' . $event->event_image) }}" alt="{{ $event->title }}" class="w-full h-64 object-cover rounded-lg mb-4">
        @endif
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-sm text-gray-600 mb-1">Total Tickets Sold</p>
            <p class="text-2xl font-bold text-gray-900">{{ $event->current_attendees }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-sm text-gray-600 mb-1">Total Orders</p>
            <p class="text-2xl font-bold text-gray-900">{{ $event->orders->where('status', 'confirmed')->count() }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-sm text-gray-600 mb-1">Total Revenue</p>
            <p class="text-2xl font-bold text-gray-900">₦{{ number_format($event->orders->where('status', 'confirmed')->sum('total_amount'), 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-sm text-gray-600 mb-1">Event Date</p>
            <p class="text-lg font-semibold text-gray-900">{{ $event->start_date->format('M d, Y') }}</p>
            <p class="text-sm text-gray-600">{{ $event->start_date->format('h:i A') }}</p>
        </div>
    </div>

    <!-- Ticket Types -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-900">Ticket Types</h2>
            <button onclick="document.getElementById('ticket-type-form').classList.toggle('hidden')" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm">
                <i class="fas fa-plus mr-2"></i>Add Ticket Type
            </button>
        </div>

        <!-- Add Ticket Type Form -->
        <div id="ticket-type-form" class="hidden mb-6 p-4 bg-gray-50 rounded-lg">
            <form action="{{ route('business.ticket-types.store', $event) }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                        <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Price (₦) *</label>
                        <input type="number" name="price" step="0.01" min="0" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                        <input type="number" name="quantity" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm">Add</button>
                    <button type="button" onclick="document.getElementById('ticket-type-form').classList.add('hidden')" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Ticket Types List -->
        @if($event->ticketTypes->count() > 0)
            <div class="space-y-3">
                @foreach($event->ticketTypes as $ticketType)
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ $ticketType->name }}</h3>
                            <p class="text-sm text-gray-600">₦{{ number_format($ticketType->price, 2) }} • {{ $ticketType->sold_quantity }} / {{ $ticketType->quantity }} sold</p>
                        </div>
                        <div class="flex gap-2">
                            <span class="px-2 py-1 text-xs rounded {{ $ticketType->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $ticketType->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-gray-600 text-center py-8">No ticket types yet. Add your first ticket type above.</p>
        @endif
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <a href="{{ route('business.events.orders', $event) }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900 mb-1">View Orders</h3>
                    <p class="text-sm text-gray-600">{{ $event->orders->count() }} total orders</p>
                </div>
                <i class="fas fa-shopping-cart text-primary text-2xl"></i>
            </div>
        </a>
        <a href="{{ route('business.events.check-in', $event) }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900 mb-1">Check-in</h3>
                    <p class="text-sm text-gray-600">Manage attendee check-ins</p>
                </div>
                <i class="fas fa-qrcode text-primary text-2xl"></i>
            </div>
        </a>
    </div>
</div>
@endsection

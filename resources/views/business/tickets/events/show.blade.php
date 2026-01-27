@extends('layouts.business')

@section('title', $event->title)
@section('page-title', $event->title)

@section('content')
<div class="space-y-4 lg:space-y-6">
    <div class="flex justify-between items-center">
        <a href="{{ route('business.tickets.events.index') }}" class="text-primary hover:text-primary/80">
            <i class="fas fa-arrow-left mr-2"></i> Back to Events
        </a>
        <div class="flex gap-2">
            @if($event->status === 'draft')
                <form action="{{ route('business.tickets.events.publish', $event) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                        <i class="fas fa-check mr-2"></i> Publish
                    </button>
                </form>
            @endif
            @if($event->status === 'published')
                <form action="{{ route('business.tickets.events.cancel', $event) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                        <i class="fas fa-times mr-2"></i> Cancel Event
                    </button>
                </form>
            @endif
            <a href="{{ route('business.tickets.events.edit', $event) }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90">
                <i class="fas fa-edit mr-2"></i> Edit
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <!-- Event Details -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2">
                <h1 class="text-3xl font-bold mb-4">{{ $event->title }}</h1>
                @if($event->cover_image)
                    <img src="{{ asset('storage/' . $event->cover_image) }}" alt="{{ $event->title }}" class="w-full h-64 object-cover rounded-lg mb-4">
                @endif
                @if($event->description)
                    <p class="text-gray-700 mb-4">{{ $event->description }}</p>
                @endif
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-gray-500">Venue</label>
                        <p class="font-semibold">{{ $event->venue }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Date & Time</label>
                        <p class="font-semibold">{{ $event->start_date->format('F d, Y h:i A') }}</p>
                    </div>
                </div>
            </div>
            <div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-semibold mb-4">Statistics</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="text-sm text-gray-500">Status</label>
                            <p>
                                <span class="px-2 py-1 text-xs rounded-full 
                                    @if($event->status === 'published') bg-green-100 text-green-800
                                    @elseif($event->status === 'draft') bg-gray-100 text-gray-800
                                    @elseif($event->status === 'cancelled') bg-red-100 text-red-800
                                    @else bg-blue-100 text-blue-800
                                    @endif">
                                    {{ ucfirst($event->status) }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-500">Total Orders</label>
                            <p class="font-semibold text-lg">{{ $stats['total_orders'] }}</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-500">Total Revenue</label>
                            <p class="font-semibold text-lg">₦{{ number_format($stats['total_revenue'], 2) }}</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-500">Commission</label>
                            <p class="font-semibold">₦{{ number_format($stats['total_commission'], 2) }}</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-500">Tickets Sold</label>
                            <p class="font-semibold">{{ $stats['total_tickets_sold'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ticket Types -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-xl font-bold mb-4">Ticket Types</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Available</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sold</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($event->ticketTypes as $ticketType)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $ticketType->name }}</div>
                                @if($ticketType->description)
                                    <div class="text-sm text-gray-500">{{ $ticketType->description }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-semibold">₦{{ number_format($ticketType->price, 2) }}</td>
                            <td class="px-4 py-3">{{ $ticketType->quantity_available }}</td>
                            <td class="px-4 py-3">{{ $ticketType->quantity_sold }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full {{ $ticketType->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $ticketType->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Recent Orders</h2>
            <a href="{{ route('business.tickets.orders.index', ['event_id' => $event->id]) }}" class="text-primary hover:text-primary/80">
                View All Orders
            </a>
        </div>
        @if($event->ticketOrders->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($event->ticketOrders as $order)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $order->order_number }}</td>
                                <td class="px-4 py-3">{{ $order->customer_name }}</td>
                                <td class="px-4 py-3 font-semibold">₦{{ number_format($order->total_amount, 2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        @if($order->payment_status === 'paid') bg-green-100 text-green-800
                                        @elseif($order->payment_status === 'pending') bg-yellow-100 text-yellow-800
                                        @else bg-red-100 text-red-800
                                        @endif">
                                        {{ ucfirst($order->payment_status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $order->created_at->format('M d, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-500 text-center py-8">No orders yet</p>
        @endif
    </div>
</div>
@endsection

@extends('layouts.business')

@section('title', 'Order Details')
@section('page-title', 'Order #' . $order->order_number)

@section('content')
<div class="space-y-4 lg:space-y-6">
    <div class="flex justify-between items-center">
        <a href="{{ route('business.tickets.orders.index') }}" class="text-primary hover:text-primary/80">
            <i class="fas fa-arrow-left mr-2"></i> Back to Orders
        </a>
    </div>

    <!-- Order Information -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-xl font-bold mb-4">Order Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-500">Order Number</label>
                <p class="font-semibold">{{ $order->order_number }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-500">Status</label>
                <p>
                    <span class="px-2 py-1 text-xs rounded-full 
                        @if($order->status === 'confirmed') bg-green-100 text-green-800
                        @elseif($order->status === 'pending') bg-yellow-100 text-yellow-800
                        @else bg-red-100 text-red-800
                        @endif">
                        {{ ucfirst($order->status) }}
                    </span>
                </p>
            </div>
            <div>
                <label class="text-sm text-gray-500">Payment Status</label>
                <p>
                    <span class="px-2 py-1 text-xs rounded-full 
                        @if($order->payment_status === 'paid') bg-green-100 text-green-800
                        @elseif($order->payment_status === 'pending') bg-yellow-100 text-yellow-800
                        @elseif($order->payment_status === 'failed') bg-red-100 text-red-800
                        @else bg-gray-100 text-gray-800
                        @endif">
                        {{ ucfirst($order->payment_status) }}
                    </span>
                </p>
            </div>
            <div>
                <label class="text-sm text-gray-500">Total Amount</label>
                <p class="font-semibold text-lg">₦{{ number_format($order->total_amount, 2) }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-500">Commission</label>
                <p class="font-semibold">₦{{ number_format($order->commission_amount, 2) }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-500">Date</label>
                <p>{{ $order->created_at->format('F d, Y h:i A') }}</p>
            </div>
        </div>
    </div>

    <!-- Customer Information -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-xl font-bold mb-4">Customer Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-500">Name</label>
                <p class="font-semibold">{{ $order->customer_name }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-500">Email</label>
                <p>{{ $order->customer_email }}</p>
            </div>
            @if($order->customer_phone)
            <div>
                <label class="text-sm text-gray-500">Phone</label>
                <p>{{ $order->customer_phone }}</p>
            </div>
            @endif
        </div>
    </div>

    <!-- Event Information -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-xl font-bold mb-4">Event Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-500">Event</label>
                <p class="font-semibold">{{ $order->event->title }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-500">Venue</label>
                <p>{{ $order->event->venue }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-500">Date</label>
                <p>{{ $order->event->start_date->format('F d, Y') }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-500">Time</label>
                <p>{{ $order->event->start_date->format('h:i A') }}</p>
            </div>
        </div>
    </div>

    <!-- Tickets -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-xl font-bold mb-4">Tickets ({{ $order->tickets->count() }})</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ticket #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Checked In</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($order->tickets as $ticket)
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium">{{ $ticket->ticket_number }}</td>
                            <td class="px-4 py-3 text-sm">{{ $ticket->ticketType->name }}</td>
                            <td class="px-4 py-3 text-sm">₦{{ number_format($ticket->ticketType->price, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    @if($ticket->status === 'valid') bg-green-100 text-green-800
                                    @elseif($ticket->status === 'used') bg-blue-100 text-blue-800
                                    @else bg-red-100 text-red-800
                                    @endif">
                                    {{ ucfirst($ticket->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($ticket->checked_in_at)
                                    {{ $ticket->checked_in_at->format('M d, Y h:i A') }}
                                @else
                                    <span class="text-gray-400">Not checked in</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

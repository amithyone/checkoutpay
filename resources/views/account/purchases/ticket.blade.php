@extends('layouts.account')
@section('title', 'Ticket Order')
@section('page-title', 'Ticket Order')
@section('content')
<div class="max-w-2xl mx-auto pb-24 lg:pb-2">
    <a href="{{ route('user.purchases') }}" class="text-sm font-medium text-primary hover:underline mb-4 inline-block">← Back to purchases</a>

    <div class="rounded-2xl bg-white border border-gray-200 shadow overflow-hidden">
        <div class="px-4 py-4 sm:px-6 border-b border-gray-100 bg-gray-50">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-lg font-bold text-gray-900">{{ $order->order_number }}</h1>
                @php
                    $statusClass = $order->payment_status === 'paid' ? 'bg-green-100 text-green-800' : ($order->payment_status === 'refunded' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800');
                @endphp
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusClass }}">{{ ucfirst($order->payment_status) }}</span>
            </div>
        </div>
        <div class="p-4 sm:p-6 space-y-4">
            @if($order->event)
                <p class="text-gray-800 font-semibold">{{ $order->event->title }}</p>
                @if($order->event->start_date)
                    <p class="text-sm text-gray-500">Event: {{ $order->event->start_date->format('M j, Y') }} @if($order->event->venue) · {{ $order->event->venue }} @endif</p>
                @endif
            @endif
            <p class="text-sm"><span class="text-gray-500">Total paid:</span> ₦{{ number_format($order->total_amount ?? 0, 2) }}</p>
            @if($order->purchased_at)
                <p class="text-sm text-gray-500">Purchased {{ $order->purchased_at->format('M j, Y g:i A') }}</p>
            @endif

            <h2 class="text-sm font-semibold text-gray-800 pt-2">Tickets & QR codes</h2>
            <p class="text-xs text-gray-500 mb-3">Show each QR code at the event for entry.</p>
            <div class="space-y-4">
                @foreach($order->tickets as $ticket)
                    <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
                        <p class="font-medium text-gray-800">{{ $ticket->ticketType->name ?? 'Ticket' }} @if($ticket->ticket_number)<span class="text-gray-500 text-sm">#{{ $ticket->ticket_number }}</span>@endif</p>
                        @if(isset($ticketQrs[$ticket->id]) && $ticketQrs[$ticket->id])
                            <img src="{{ $ticketQrs[$ticket->id] }}" alt="Ticket QR" class="mt-2 w-32 h-32 object-contain border border-gray-200 rounded-lg bg-white p-1">
                        @else
                            <p class="text-sm text-gray-500 mt-2">QR code not available</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

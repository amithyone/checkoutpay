@extends('layouts.account')
@section('title', 'Purchases')
@section('page-title', 'Purchases')
@section('content')
<div class="max-w-4xl mx-auto">
<a href="{{ route('user.dashboard') }}" class="text-sm font-medium text-primary hover:underline mb-4 inline-block">← Back to dashboard</a>
<h2 class="text-lg font-bold text-gray-800 mb-3">Rentals</h2>
@if($rentals->count() > 0)
<ul class="space-y-2 mb-8">
@foreach($rentals as $rental)
<li class="bg-white rounded-xl border border-gray-200 p-4"><span class="font-medium">{{ $rental->rental_number }}</span> @if($rental->business) {{ $rental->business->name }} @endif <span class="text-sm">₦{{ number_format($rental->total_amount, 2) }}</span></li>
@endforeach
</ul>
@else
<p class="text-gray-600 mb-8">No rentals yet.</p>
@endif
<h2 class="text-lg font-bold text-gray-800 mb-3">Tickets</h2>
@if($ticketOrders->count() > 0)
<ul class="space-y-2 mb-8">
@foreach($ticketOrders as $order)
<li class="bg-white rounded-xl border p-4"><span class="font-medium">{{ $order->event->title ?? 'Event' }}</span> <a href="{{ route('tickets.order', $order->order_number) }}" class="text-primary text-sm">View</a></li>
@endforeach
</ul>
@else
<p class="text-gray-600 mb-8">No ticket orders.</p>
@endif
<h2 class="text-lg font-bold text-gray-800 mb-3">Memberships</h2>
@if($memberships->count() > 0)
<ul class="space-y-2">
@foreach($memberships as $sub)
<li class="bg-white rounded-xl border p-4">{{ $sub->membership->name ?? 'Membership' }} · {{ $sub->expires_at?->format('M j, Y') }}</li>
@endforeach
</ul>
@else
<p class="text-gray-600">No memberships.</p>
@endif
</div>
@endsection

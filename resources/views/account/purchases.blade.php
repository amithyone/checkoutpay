@extends('layouts.account')
@section('title', 'Purchases')
@section('page-title', 'Purchases')
@section('content')
@php $accentColor = \App\Models\Setting::get('rentals_accent_color', '#000000'); @endphp
<div class="max-w-4xl mx-auto pb-24 lg:pb-2">
    <a href="{{ route('user.dashboard') }}" class="text-sm font-medium hover:underline mb-4 inline-block" style="color: {{ $accentColor }};">← Back to dashboard</a>

    {{-- Rentals --}}
    <section class="rounded-2xl bg-white border border-gray-200 shadow overflow-hidden mb-6">
        <div class="px-4 py-3 sm:px-5 sm:py-4 border-b border-gray-100 flex items-center gap-3" style="background-color: {{ $accentColor }}15;">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white flex-shrink-0" style="background-color: {{ $accentColor }};">
                <i class="fas fa-toolbox"></i>
            </div>
            <h2 class="text-lg font-bold text-gray-800">Rentals</h2>
        </div>
        <div class="p-4 sm:p-5">
            @if($rentals->count() > 0)
                <ul class="space-y-3">
                    @foreach($rentals as $rental)
                        <li>
                            <a href="{{ route('user.purchases.rental', $rental) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-gray-50 border border-gray-200 px-4 py-3 sm:py-3.5 hover:bg-gray-100 hover:border-gray-300 transition block">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-gray-900 truncate">{{ $rental->rental_number }}</p>
                                    @if($rental->business)
                                        <p class="text-sm text-gray-500 mt-0.5">{{ $rental->business->name }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    <span class="font-semibold text-gray-800">₦{{ number_format($rental->total_amount, 2) }}</span>
                                    <i class="fas fa-chevron-right text-gray-400 text-sm"></i>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="text-center py-8 sm:py-10">
                    <p class="text-gray-600 mb-4">No rentals yet.</p>
                    <a href="{{ route('rentals.index') }}" class="inline-flex items-center gap-2 rounded-xl text-white px-4 py-2.5 font-medium text-sm hover:opacity-90" style="background-color: {{ $accentColor }};">
                        <i class="fas fa-search"></i> Browse rentals
                    </a>
                </div>
            @endif
        </div>
    </section>

    {{-- Tickets --}}
    <section class="rounded-2xl bg-white border border-gray-200 shadow overflow-hidden mb-6">
        <div class="px-4 py-3 sm:px-5 sm:py-4 border-b border-gray-100 flex items-center gap-3" style="background-color: {{ $accentColor }}15;">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white flex-shrink-0" style="background-color: {{ $accentColor }};">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <h2 class="text-lg font-bold text-gray-800">Tickets</h2>
        </div>
        <div class="p-4 sm:p-5">
            @if($ticketOrders->count() > 0)
                <ul class="space-y-3">
                    @foreach($ticketOrders as $order)
                        <li>
                            <a href="{{ route('user.purchases.ticket', $order->order_number) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-gray-50 border border-gray-200 px-4 py-3 sm:py-3.5 hover:bg-gray-100 hover:border-gray-300 transition block">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-gray-900 truncate">{{ $order->event->title ?? 'Event' }}</p>
                                    @if($order->purchased_at)
                                        <p class="text-sm text-gray-500 mt-0.5">{{ $order->purchased_at->format('M j, Y') }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    <span class="text-sm text-gray-500">{{ $order->order_number }}</span>
                                    <i class="fas fa-chevron-right text-gray-400 text-sm"></i>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="text-center py-8 sm:py-10">
                    <p class="text-gray-600 mb-4">No ticket orders yet.</p>
                    <a href="{{ route('tickets.index') }}" class="inline-flex items-center gap-2 rounded-xl text-white px-4 py-2.5 font-medium text-sm hover:opacity-90" style="background-color: {{ $accentColor }};">
                        <i class="fas fa-search"></i> Browse events
                    </a>
                </div>
            @endif
        </div>
    </section>

    {{-- Memberships --}}
    <section class="rounded-2xl bg-white border border-gray-200 shadow overflow-hidden mb-6">
        <div class="px-4 py-3 sm:px-5 sm:py-4 border-b border-gray-100 flex items-center gap-3" style="background-color: {{ $accentColor }}15;">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white flex-shrink-0" style="background-color: {{ $accentColor }};">
                <i class="fas fa-id-card"></i>
            </div>
            <h2 class="text-lg font-bold text-gray-800">Memberships</h2>
        </div>
        <div class="p-4 sm:p-5">
            @if($memberships->count() > 0)
                <ul class="space-y-3">
                    @foreach($memberships as $sub)
                        <li>
                            <a href="{{ route('user.purchases.membership', $sub) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-gray-50 border border-gray-200 px-4 py-3 sm:py-3.5 hover:bg-gray-100 hover:border-gray-300 transition block">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-gray-900 truncate">{{ $sub->membership->name ?? 'Membership' }}</p>
                                    @if($sub->expires_at)
                                        <p class="text-sm text-gray-500 mt-0.5">Expires {{ $sub->expires_at->format('M j, Y') }}</p>
                                    @endif
                                </div>
                                <i class="fas fa-chevron-right text-gray-400 text-sm flex-shrink-0"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="text-center py-8 sm:py-10">
                    <p class="text-gray-600 mb-4">No memberships yet.</p>
                    <a href="{{ route('memberships.index') }}" class="inline-flex items-center gap-2 rounded-xl text-white px-4 py-2.5 font-medium text-sm hover:opacity-90" style="background-color: {{ $accentColor }};">
                        <i class="fas fa-search"></i> Browse memberships
                    </a>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection

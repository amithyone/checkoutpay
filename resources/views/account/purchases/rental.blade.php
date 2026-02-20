@extends('layouts.account')
@section('title', 'Rental Details')
@section('page-title', 'Rental Details')
@section('content')
<div class="max-w-2xl mx-auto pb-24 lg:pb-2">
    <a href="{{ route('user.purchases') }}" class="text-sm font-medium text-primary hover:underline mb-4 inline-block">← Back to purchases</a>

    <div class="rounded-2xl bg-white border border-gray-200 shadow overflow-hidden">
        <div class="px-4 py-4 sm:px-6 border-b border-gray-100 bg-gray-50">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-lg font-bold text-gray-900">{{ $rental->rental_number }}</h1>
                @php
                    $statusClass = match($rental->status) {
                        'pending' => 'bg-amber-100 text-amber-800',
                        'approved', 'active' => 'bg-blue-100 text-blue-800',
                        'completed' => 'bg-green-100 text-green-800',
                        'cancelled', 'rejected' => 'bg-red-100 text-red-800',
                        default => 'bg-gray-100 text-gray-800',
                    };
                @endphp
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusClass }}">{{ ucfirst($rental->status) }}</span>
            </div>
        </div>
        <div class="p-4 sm:p-6 space-y-4">
            @if($rental->business)
                <p class="text-gray-600"><span class="font-medium text-gray-700">Business:</span> {{ $rental->business->name }}</p>
            @endif
            <div class="grid grid-cols-2 gap-3 text-sm">
                <p><span class="text-gray-500">Start:</span> {{ $rental->start_date?->format('M j, Y') }}</p>
                <p><span class="text-gray-500">End:</span> {{ $rental->end_date?->format('M j, Y') }}</p>
                <p><span class="text-gray-500">Days:</span> {{ $rental->days ?? '–' }}</p>
                <p><span class="text-gray-500">Total:</span> ₦{{ number_format($rental->total_amount ?? 0, 2) }}</p>
            </div>
            @if($rental->items && $rental->items->count() > 0)
                <div>
                    <h2 class="text-sm font-semibold text-gray-800 mb-2">Items</h2>
                    <ul class="space-y-1 text-sm text-gray-600">
                        @foreach($rental->items as $item)
                            <li>{{ $item->name }} × {{ $item->pivot->quantity ?? 1 }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="pt-4 border-t border-gray-200">
                <p class="text-sm font-medium text-gray-700 mb-2">QR code</p>
                <p class="text-xs text-gray-500 mb-2">Show this at pickup or return for verification.</p>
                <img src="{{ $rentalQrBase64 }}" alt="Rental QR code" class="w-40 h-40 object-contain border border-gray-200 rounded-xl p-2 bg-white">
            </div>
        </div>
    </div>
</div>
@endsection

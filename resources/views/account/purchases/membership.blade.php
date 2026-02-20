@extends('layouts.account')
@section('title', 'Membership Details')
@section('page-title', 'Membership Details')
@section('content')
<div class="max-w-2xl mx-auto pb-24 lg:pb-2">
    <a href="{{ route('user.purchases') }}" class="text-sm font-medium text-primary hover:underline mb-4 inline-block">← Back to purchases</a>

    <div class="rounded-2xl bg-white border border-gray-200 shadow overflow-hidden">
        <div class="px-4 py-4 sm:px-6 border-b border-gray-100 bg-gray-50">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-lg font-bold text-gray-900">{{ $membership->membership->name ?? 'Membership' }}</h1>
                @php
                    $isActive = $membership->status === 'active' && $membership->expires_at && $membership->expires_at->isFuture();
                    $statusClass = $isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
                @endphp
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusClass }}">{{ $isActive ? 'Active' : ucfirst($membership->status ?? 'Inactive') }}</span>
            </div>
        </div>
        <div class="p-4 sm:p-6 space-y-4">
            <p class="text-sm"><span class="text-gray-500">Subscription #:</span> {{ $membership->subscription_number }}</p>
            @if($membership->membership && $membership->membership->business)
                <p class="text-sm"><span class="text-gray-500">Provider:</span> {{ $membership->membership->business->name }}</p>
            @endif
            @if($membership->start_date)
                <p class="text-sm"><span class="text-gray-500">Start:</span> {{ $membership->start_date->format('M j, Y') }}</p>
            @endif
            @if($membership->expires_at)
                <p class="text-sm"><span class="text-gray-500">Expires:</span> {{ $membership->expires_at->format('M j, Y') }}</p>
            @endif
            <div class="pt-4 border-t border-gray-200">
                <p class="text-sm font-medium text-gray-700 mb-2">QR code</p>
                <p class="text-xs text-gray-500 mb-2">Show this to verify your membership.</p>
                <img src="{{ $membershipQrBase64 }}" alt="Membership QR code" class="w-40 h-40 object-contain border border-gray-200 rounded-xl p-2 bg-white">
            </div>
        </div>
    </div>
</div>
@endsection

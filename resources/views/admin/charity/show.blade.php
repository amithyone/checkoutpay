@extends('layouts.admin')

@section('title', $campaign->title)
@section('page-title', 'Campaign')

@section('content')
<div class="space-y-6">
    <a href="{{ route('admin.charity.index') }}" class="text-primary hover:underline text-sm">Back to campaigns</a>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        @if($campaign->image)
            <img src="{{ asset('storage/' . $campaign->image) }}" alt="{{ $campaign->title }}" class="w-full h-64 object-cover">
        @endif
        <div class="p-6">
            <span class="px-2 py-0.5 rounded text-xs font-medium
                @if($campaign->status === 'approved') bg-green-100 text-green-800
                @elseif($campaign->status === 'pending') bg-yellow-100 text-yellow-800
                @else bg-red-100 text-red-800 @endif">{{ $campaign->status }}</span>
            @if($campaign->is_featured)<span class="ml-2 text-xs text-primary">Featured</span>@endif
            <h1 class="text-xl font-bold text-gray-900 mt-2">{{ $campaign->title }}</h1>
            <p class="text-gray-600">By {{ $campaign->business->name ?? '—' }}</p>
            <p class="mt-2">{{ $campaign->currency }} {{ number_format($campaign->raised_amount, 0) }} / {{ number_format($campaign->goal_amount, 0) }}</p>
            <div class="mt-4 prose prose-gray max-w-none">{!! nl2br(e($campaign->story)) !!}</div>
        </div>
    </div>
    <div class="flex gap-3">
        <a href="{{ route('admin.charity.edit', $campaign) }}" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">Edit</a>
        <a href="{{ route('charity.show', $campaign->slug) }}" target="_blank" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">View public page</a>
    </div>
</div>
@endsection

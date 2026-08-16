@extends('layouts.admin')

@section('title', 'API hit')
@section('page-title', 'API hit')

@section('content')
<div class="max-w-3xl space-y-4">
    <a href="{{ route('admin.api-hits.index') }}" class="text-sm text-primary hover:underline">&larr; Back to API hits</a>
    <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-3 text-sm">
        <div class="flex items-center gap-2">
            @if($apiHit->successful)
                <span class="px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 rounded-full">Successful</span>
            @else
                <span class="px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800 rounded-full">Unsuccessful</span>
            @endif
            <span class="text-gray-500">{{ $apiHit->status_code }}</span>
        </div>
        <p><span class="text-gray-500">When:</span> {{ $apiHit->created_at?->format('M d, Y H:i:s') }}</p>
        <p><span class="text-gray-500">Endpoint:</span> <span class="font-mono">{{ $apiHit->method }} {{ $apiHit->path }}</span></p>
        <p><span class="text-gray-500">Website:</span> {{ $apiHit->website_host ?: '—' }}</p>
        <p><span class="text-gray-500">Origin:</span> <span class="break-all">{{ $apiHit->origin ?: '—' }}</span></p>
        <p><span class="text-gray-500">Referer:</span> <span class="break-all">{{ $apiHit->referer ?: '—' }}</span></p>
        <p><span class="text-gray-500">Business:</span>
            @if($apiHit->business)
                <a href="{{ route('admin.businesses.show', $apiHit->business) }}" class="text-primary hover:underline">{{ $apiHit->business->name }}</a>
            @else
                —
            @endif
        </p>
        <p><span class="text-gray-500">IP:</span> {{ $apiHit->ip ?: '—' }}</p>
        <p><span class="text-gray-500">API key hint:</span> {{ $apiHit->api_key_hint ? $apiHit->api_key_hint.'…' : '—' }}</p>
        <p><span class="text-gray-500">Duration:</span> {{ $apiHit->duration_ms !== null ? $apiHit->duration_ms.' ms' : '—' }}</p>
        <p><span class="text-gray-500">Message:</span> {{ $apiHit->message ?: '—' }}</p>
        <p><span class="text-gray-500">User agent:</span> <span class="break-all text-xs text-gray-600">{{ $apiHit->user_agent ?: '—' }}</span></p>
    </div>
</div>
@endsection

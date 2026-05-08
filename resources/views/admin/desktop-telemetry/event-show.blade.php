@extends('layouts.admin')

@section('title', 'Event ' . $event->event_id)
@section('page-title', 'Telemetry event')

@section('content')
<div class="max-w-4xl space-y-4">
    <a href="{{ route('admin.desktop-telemetry.events.index') }}" class="text-sm text-primary hover:underline">&larr; Back to events</a>

    <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div><span class="text-gray-500">Tenant:</span> <span class="font-medium">{{ $event->tenant_id }}</span></div>
            <div><span class="text-gray-500">App role:</span> <span class="font-medium">{{ $event->app_role }}</span></div>
            <div><span class="text-gray-500">Instance:</span> <span class="font-mono">{{ $event->app_instance_id }}</span></div>
            <div><span class="text-gray-500">Event id:</span> <span class="font-mono">{{ $event->event_id }}</span></div>
            <div><span class="text-gray-500">Type:</span> <span class="font-medium">{{ $event->event_type }}</span></div>
            <div><span class="text-gray-500">App version:</span> <span class="font-medium">{{ $event->app_version ?? '—' }}</span></div>
            <div><span class="text-gray-500">Event ts:</span> <span class="font-medium">{{ $event->event_ts?->format('Y-m-d H:i:s') }}</span></div>
            <div><span class="text-gray-500">Received at:</span> <span class="font-medium">{{ $event->received_at?->format('Y-m-d H:i:s') }}</span></div>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-4">
        <p class="text-xs text-gray-500 mb-2">Payload</p>
        <pre class="text-xs bg-gray-50 border border-gray-200 rounded p-3 overflow-x-auto">{{ json_encode($event->payload_json ?: new \stdClass(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 p-4">
        <p class="text-xs text-gray-500 mb-2">Context</p>
        <pre class="text-xs bg-gray-50 border border-gray-200 rounded p-3 overflow-x-auto">{{ json_encode($event->context_json ?: new \stdClass(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
</div>
@endsection

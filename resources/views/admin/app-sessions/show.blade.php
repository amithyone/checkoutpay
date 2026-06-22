@extends('layouts.admin')

@section('title', 'App session')
@section('page-title', 'App session detail')

@section('content')
<div class="space-y-6">
    @include('admin.whatsapp-wallet.partials.nav')

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.app-sessions.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; All sessions</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 font-mono">{{ $session->phone_e164 }}</h3>
                <p class="text-sm text-gray-600 mt-1">{{ $session->wallet?->kyc_fname }} {{ $session->wallet?->kyc_lname }}</p>
            </div>
            @if($session->isActive())
                <span class="text-xs font-semibold text-green-700 bg-green-50 px-3 py-1 rounded-full">Active session</span>
            @else
                <span class="text-xs text-gray-600 bg-gray-100 px-3 py-1 rounded-full">Ended {{ $session->ended_at?->format('Y-m-d H:i') }}</span>
            @endif
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Session UUID</dt>
                <dd class="font-mono text-xs mt-0.5 break-all">{{ $session->session_uuid }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Login method</dt>
                <dd class="mt-0.5">{{ $session->loginMethodLabel() }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Platform / version</dt>
                <dd class="mt-0.5 capitalize">{{ $session->platform ?? '—' }} @if($session->app_version)<span class="text-gray-500">· {{ $session->app_version }}</span>@endif</dd>
            </div>
            <div>
                <dt class="text-gray-500">Device</dt>
                <dd class="mt-0.5">{{ $session->device_label ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Started</dt>
                <dd class="mt-0.5">{{ $session->started_at?->format('Y-m-d H:i:s') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Last seen</dt>
                <dd class="mt-0.5">{{ $session->last_seen_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">IP</dt>
                <dd class="mt-0.5 font-mono text-xs">{{ $session->ip_address ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-gray-500">User agent</dt>
                <dd class="mt-0.5 text-xs text-gray-700 break-all">{{ $session->user_agent ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100">
            <h4 class="font-semibold text-gray-900">Activity</h4>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Time</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Event</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Summary</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($session->events as $event)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $event->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-medium bg-gray-100 text-gray-800 px-2 py-0.5 rounded">
                                    {{ $eventTypes[$event->event_type] ?? $event->event_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ $event->summary }}</div>
                                @if(is_array($event->meta) && $event->meta !== [])
                                    <pre class="text-xs text-gray-500 mt-1 whitespace-pre-wrap">{{ json_encode($event->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $event->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">No events recorded for this session.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

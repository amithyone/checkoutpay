@extends('layouts.admin')

@section('title', 'App session events')
@section('page-title', 'WhatsApp wallet — App activity')

@section('content')
<div class="space-y-6">
    @include('admin.whatsapp-wallet.partials.nav')

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">App activity log</h3>
            <p class="text-sm text-gray-600 mt-1">Login, logout, transfers, passkey setup, and device step-up events across all sessions.</p>
        </div>
        <a href="{{ route('admin.app-sessions.index') }}" class="text-sm text-primary hover:underline">View sessions &rarr;</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" action="{{ route('admin.app-sessions.events') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Phone or summary…"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Event type</label>
                <select name="event_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach($eventTypes as $value => $label)
                        <option value="{{ $value }}" @selected(request('event_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2 lg:col-span-5">
                <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm">Filter</button>
                <a href="{{ route('admin.app-sessions.events') }}" class="text-sm text-gray-600 py-2">Reset</a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Time</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Phone</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Event</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Summary</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($events as $event)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $event->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $event->phone_e164 ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-medium bg-gray-100 text-gray-800 px-2 py-0.5 rounded">
                                    {{ $eventTypes[$event->event_type] ?? $event->event_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 max-w-md truncate" title="{{ $event->summary }}">{{ $event->summary }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($event->session)
                                    <a href="{{ route('admin.app-sessions.show', $event->session) }}" class="text-primary hover:underline text-xs">Session</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">No events yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($events->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $events->links() }}</div>
        @endif
    </div>
</div>
@endsection

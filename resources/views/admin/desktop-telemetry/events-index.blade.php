@extends('layouts.admin')

@section('title', 'Desktop telemetry')
@section('page-title', 'Desktop telemetry events')

@section('content')
<div class="space-y-6">
    @if(session('success'))<div class="p-3 bg-green-50 border border-green-200 text-green-800 rounded text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="p-3 bg-red-50 border border-red-200 text-red-800 rounded text-sm">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-xs text-gray-500">Total events</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-xs text-gray-500">Last 24h</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['last_24h']) }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-xs text-gray-500">Unique app instances</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['unique_instances']) }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-xs text-gray-500">Last event</p>
            <p class="text-sm font-semibold text-gray-900 mt-1">{{ $stats['last_event_at'] ? \Illuminate\Support\Carbon::parse($stats['last_event_at'])->diffForHumans() : '—' }}</p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.desktop-telemetry.policies.index') }}" class="px-3 py-1.5 text-sm bg-primary text-white rounded-lg">Policies</a>
        <a href="{{ route('admin.desktop-telemetry.tokens.index') }}" class="px-3 py-1.5 text-sm bg-gray-100 text-gray-800 rounded-lg">App tokens</a>
    </div>

    <form method="GET" class="bg-white border border-gray-200 rounded-lg p-4 grid grid-cols-1 md:grid-cols-5 gap-3 text-sm">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search instance/event/tenant" class="px-3 py-2 border border-gray-300 rounded-lg md:col-span-2">
        <select name="role" class="px-3 py-2 border border-gray-300 rounded-lg">
            <option value="">Any role</option>
            <option value="admin" @selected(request('role') === 'admin')>admin</option>
            <option value="player" @selected(request('role') === 'player')>player</option>
        </select>
        <input type="text" name="type" value="{{ request('type') }}" placeholder="Event type" class="px-3 py-2 border border-gray-300 rounded-lg">
        <input type="text" name="tenant_id" value="{{ request('tenant_id') }}" placeholder="Tenant id" class="px-3 py-2 border border-gray-300 rounded-lg">
        <button class="px-3 py-2 bg-primary text-white rounded-lg md:col-span-1">Filter</button>
    </form>

    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">When</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Tenant</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Role</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Instance</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Type</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">App version</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($events as $e)
                    <tr>
                        <td class="px-4 py-2 text-xs text-gray-700 whitespace-nowrap">{{ $e->event_ts?->format('Y-m-d H:i:s') }}</td>
                        <td class="px-4 py-2 text-xs text-gray-700">{{ $e->tenant_id }}</td>
                        <td class="px-4 py-2 text-xs"><span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700">{{ $e->app_role }}</span></td>
                        <td class="px-4 py-2 text-xs font-mono text-gray-700 truncate max-w-[160px]" title="{{ $e->app_instance_id }}">{{ Str::limit($e->app_instance_id, 18) }}</td>
                        <td class="px-4 py-2 text-xs text-gray-900 font-medium">{{ $e->event_type }}</td>
                        <td class="px-4 py-2 text-xs text-gray-500">{{ $e->app_version ?? '—' }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.desktop-telemetry.events.show', $e) }}" class="text-xs text-primary hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">No events yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t">{{ $events->links() }}</div>
    </div>
</div>
@endsection

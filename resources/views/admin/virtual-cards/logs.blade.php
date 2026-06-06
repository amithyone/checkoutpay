@extends('layouts.admin')

@section('title', 'Card Request Logs')
@section('page-title', 'Card Request Logs')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Card request &amp; webhook logs</h2>
            <p class="text-sm text-gray-600 mt-1">Fee debits, MevonPay responses, webhooks, refunds, and activations</p>
        </div>
        <a href="{{ route('admin.virtual-cards.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
            <i class="fas fa-arrow-left mr-1"></i> Back to Card Management
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Event</label>
                <input type="text" name="event" value="{{ request('event') }}" placeholder="webhook_received"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Level</label>
                <select name="level" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All</option>
                    <option value="info" @selected(request('level') === 'info')>Info</option>
                    <option value="warning" @selected(request('level') === 'warning')>Warning</option>
                    <option value="error" @selected(request('level') === 'error')>Error</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Request ID</label>
                <input type="number" name="request_id" value="{{ request('request_id') }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-28">
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-500 mb-1">Search</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Reference, card_id, message"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded-lg text-sm">Filter</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Time</th>
                        <th class="px-4 py-3">Level</th>
                        <th class="px-4 py-3">Event</th>
                        <th class="px-4 py-3">Request</th>
                        <th class="px-4 py-3">Message</th>
                        <th class="px-4 py-3">Context</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($logs as $log)
                        <tr class="align-top hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $log->created_at?->format('M d H:i:s') }}</td>
                            <td class="px-4 py-3">
                                @if($log->level === 'error')
                                    <span class="text-red-700 font-medium">error</span>
                                @elseif($log->level === 'warning')
                                    <span class="text-amber-700 font-medium">warning</span>
                                @else
                                    <span class="text-gray-700">info</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $log->event }}</td>
                            <td class="px-4 py-3">
                                @if($log->request)
                                    <a href="{{ route('admin.virtual-cards.show', $log->request) }}" class="text-primary hover:underline">#{{ $log->request->id }}</a>
                                    <div class="text-xs text-gray-500">{{ $log->request->wallet?->phone_e164 }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-900">{{ $log->message }}</td>
                            <td class="px-4 py-3">
                                @include('admin.virtual-cards._log-context', ['log' => $log])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">No card logs yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
@endsection

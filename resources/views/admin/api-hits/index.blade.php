@extends('layouts.admin')

@section('title', 'API hits')
@section('page-title', 'API hits')

@section('content')
<div class="space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <p class="text-xs text-gray-500">Successful</p>
            <p class="text-2xl font-semibold text-green-700">{{ number_format($stats['success']) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <p class="text-xs text-gray-500">Unsuccessful</p>
            <p class="text-2xl font-semibold text-red-700">{{ number_format($stats['failed']) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <p class="text-sm text-gray-600 mb-3">Every merchant API call (payment-request, banks, withdrawal, etc.). Website is taken from Origin/Referer when the browser sends them, or from <code class="bg-gray-100 px-1 rounded">website_url</code> / <code class="bg-gray-100 px-1 rounded">webhook_url</code> / the business’s saved site when the call comes from a server.</p>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <select name="result" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All results</option>
                <option value="success" @selected(request('result') === 'success')>Successful</option>
                <option value="failed" @selected(request('result') === 'failed')>Unsuccessful</option>
            </select>
            <input type="text" name="website" value="{{ request('website') }}" placeholder="Website host" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <input type="text" name="path" value="{{ request('path') }}" placeholder="Path e.g. payment-request" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <select name="business_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All businesses</option>
                @foreach($businesses as $business)
                    <option value="{{ $business->id }}" @selected((string) request('business_id') === (string) $business->id)>{{ $business->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm">Filter</button>
                <a href="{{ route('admin.api-hits.index') }}" class="px-4 py-2 text-sm text-gray-600">Clear</a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">When</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Website</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Endpoint</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Result</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{{ $log->created_at?->format('M d, H:i:s') }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            {{ $log->website_host ?: '—' }}
                            @if($log->origin)
                                <div class="text-xs text-gray-400 truncate max-w-xs">{{ $log->origin }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm font-mono text-gray-800">
                            <span class="text-xs text-gray-500">{{ $log->method }}</span> {{ $log->path }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $log->business->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if($log->successful)
                                <span class="px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 rounded-full">{{ $log->status_code }} OK</span>
                            @else
                                <span class="px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800 rounded-full">{{ $log->status_code }} fail</span>
                            @endif
                            @if($log->message)
                                <div class="text-xs text-gray-500 mt-1 max-w-xs truncate">{{ $log->message }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.api-hits.show', $log) }}" class="text-sm text-primary hover:underline">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No API hits yet. They appear when a website calls /api/v1 with an API key (or without one).</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
@endsection

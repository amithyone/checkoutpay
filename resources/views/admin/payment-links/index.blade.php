@extends('layouts.admin')

@section('title', 'Payment links')
@section('page-title', 'Payment links')

@section('content')
<div class="space-y-6">
    <div>
        <h3 class="text-lg font-semibold text-gray-900">All payment links</h3>
        <p class="text-sm text-gray-600 mt-1">Links created by businesses to collect without a website</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-sm text-gray-600 mb-1">Total</p>
            <h3 class="text-2xl font-bold">{{ number_format($stats['total']) }}</h3>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-sm text-gray-600 mb-1">Active</p>
            <h3 class="text-2xl font-bold text-green-600">{{ number_format($stats['active']) }}</h3>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-sm text-gray-600 mb-1">Paid (one-time)</p>
            <h3 class="text-2xl font-bold">{{ number_format($stats['paid']) }}</h3>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-sm text-gray-600 mb-1">Collected</p>
            <h3 class="text-2xl font-bold">₦{{ number_format($stats['collected_amount'] ?? 0, 2) }}</h3>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" action="{{ route('admin.payment-links.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Title, code, business" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <select name="business_id" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">All businesses</option>
                @foreach($businesses as $business)
                    <option value="{{ $business->id }}" @selected((string) request('business_id') === (string) $business->id)>{{ $business->name }}</option>
                @endforeach
            </select>
            <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">All statuses</option>
                @foreach(['active','paused','paid','cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <select name="reuse_mode" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Any type</option>
                    <option value="one_time" @selected(request('reuse_mode') === 'one_time')>One-time</option>
                    <option value="reusable" @selected(request('reuse_mode') === 'reusable')>Reusable</option>
                </select>
                <button class="px-4 py-2 bg-primary text-white rounded-lg text-sm">Filter</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Collected</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($links as $link)
                    <tr>
                        <td class="px-4 py-3 text-sm">{{ $link->business->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium">{{ $link->title }}</div>
                            <div class="text-xs font-mono text-gray-500">{{ $link->code }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm">{{ $link->formattedAmount() }}</td>
                        <td class="px-4 py-3 text-sm">{{ $link->isReusable() ? 'Reusable' : 'One-time' }}</td>
                        <td class="px-4 py-3 text-sm">{{ ucfirst($link->status) }}</td>
                        <td class="px-4 py-3 text-sm">₦{{ number_format($link->collected_amount, 2) }} ({{ $link->collected_count }})</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('admin.payment-links.show', $link) }}" class="text-primary text-sm">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">No payment links created yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $links->withQueryString()->links() }}</div>
</div>
@endsection

@extends('layouts.business')

@section('title', 'Payment links')
@section('page-title', 'Payment links')

@section('content')
<div class="space-y-4 lg:space-y-6 pb-20 lg:pb-0">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-xl lg:text-2xl font-bold text-gray-900">Payment links</h2>
            <p class="text-sm text-gray-600 mt-1">Create a product or payment page and send the link to your client. No website required.</p>
        </div>
        <a href="{{ route('business.payment-links.create') }}" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium flex items-center gap-2">
            <i class="fas fa-plus"></i>
            Create payment link
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-600 mb-1">Total links</p>
            <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['total']) }}</h3>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-600 mb-1">Active</p>
            <h3 class="text-2xl font-bold text-green-600">{{ number_format($stats['active']) }}</h3>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-600 mb-1">Completed (one-time)</p>
            <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['paid']) }}</h3>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-600 mb-1">Collected</p>
            <h3 class="text-2xl font-bold text-gray-900">₦{{ number_format($stats['collected_amount'] ?? 0, 2) }}</h3>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <form method="GET" action="{{ route('business.payment-links.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Title or code" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
            <select name="status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                <option value="">All statuses</option>
                @foreach(['active' => 'Active', 'paused' => 'Paused', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <select name="reuse_mode" class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg">
                    <option value="">One-time or reusable</option>
                    <option value="one_time" @selected(request('reuse_mode') === 'one_time')>One-time</option>
                    <option value="reusable" @selected(request('reuse_mode') === 'reusable')>Reusable</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm"><i class="fas fa-search"></i></button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Collected</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($links as $link)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">{{ $link->title }}</div>
                            <div class="text-xs text-gray-500 font-mono">{{ $link->code }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm">{{ $link->formattedAmount() }}</td>
                        <td class="px-4 py-3 text-sm">{{ $link->isReusable() ? 'Reusable' : 'One-time' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs font-medium rounded-full
                                @if($link->status === 'active') bg-green-100 text-green-800
                                @elseif($link->status === 'paid') bg-blue-100 text-blue-800
                                @elseif($link->status === 'paused') bg-yellow-100 text-yellow-800
                                @else bg-gray-100 text-gray-800 @endif">{{ ucfirst($link->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm">₦{{ number_format($link->collected_amount, 2) }} ({{ $link->collected_count }})</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('business.payment-links.show', $link) }}" class="text-primary text-sm font-medium">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No payment links yet. Create one to collect without a website.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $links->withQueryString()->links() }}</div>
</div>
@endsection

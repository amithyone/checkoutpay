@extends('layouts.admin')

@section('title', 'Audits')
@section('page-title', 'Audits')

@section('content')
@php
    $fmt = fn (float $n): string => '₦'.number_format($n, 2);
@endphp
<div class="space-y-6">
    <div>
        <h2 class="text-lg font-semibold text-gray-900">Bank float (customer liabilities)</h2>
        <p class="text-sm text-gray-600 mt-1">
            Money we should hold in the bank for customer balances. Test businesses and wallets marked
            <span class="font-medium">Exclude from bank float audit</span> are left out of these totals.
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Business balances</p>
            <p class="mt-2 text-2xl font-bold text-gray-900">{{ $fmt($float['business']['total']) }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ number_format($float['business']['count']) }} merchants included</p>
            @if($float['business']['exempt_count'] > 0)
                <p class="mt-1 text-xs text-amber-700">Exempt: {{ $fmt($float['business']['exempt_total']) }} ({{ $float['business']['exempt_count'] }})</p>
            @endif
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Wallet balances</p>
            <p class="mt-2 text-2xl font-bold text-gray-900">{{ $fmt($float['wallet']['total']) }}</p>
            <p class="mt-1 text-xs text-gray-500">
                Personal {{ $fmt($float['wallet']['personal']) }}
                · Savings {{ $fmt($float['wallet']['savings']) }}
                · Biz ledger {{ $fmt($float['wallet']['business_standalone']) }}
            </p>
            @if($float['wallet']['exempt_count'] > 0)
                <p class="mt-1 text-xs text-amber-700">Exempt: {{ $fmt($float['wallet']['exempt_total']) }} ({{ $float['wallet']['exempt_count'] }})</p>
            @endif
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Rentals balances</p>
            <p class="mt-2 text-2xl font-bold text-gray-900">{{ $fmt($float['rentals']['total']) }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ number_format($float['rentals']['count']) }} renters included</p>
            @if($float['rentals']['exempt_count'] > 0)
                <p class="mt-1 text-xs text-amber-700">Exempt: {{ $fmt($float['rentals']['exempt_total']) }} ({{ $float['rentals']['exempt_count'] }})</p>
            @endif
        </div>

        <div class="bg-indigo-50 rounded-lg border border-indigo-200 p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-indigo-700">Site total (bank float)</p>
            <p class="mt-2 text-2xl font-bold text-indigo-900">{{ $fmt($float['site']['total']) }}</p>
            <p class="mt-1 text-xs text-indigo-700">Business + wallet + rentals (non-exempt)</p>
            @if($float['site']['exempt_total'] > 0)
                <p class="mt-1 text-xs text-amber-800">Excluded tests: {{ $fmt($float['site']['exempt_total']) }}</p>
            @endif
        </div>
    </div>

    @if(count($float['exempt_businesses']) > 0 || count($float['exempt_wallets']) > 0)
        <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm space-y-4">
            <h3 class="text-sm font-semibold text-gray-900">Exempt from float calculation</h3>
            <p class="text-xs text-gray-500">Toggle on the business or wallet show page. Linked merchant balances stay on the business card only.</p>

            @if(count($float['exempt_businesses']) > 0)
                <div>
                    <p class="text-xs font-medium text-gray-600 mb-2">Businesses</p>
                    <ul class="divide-y divide-gray-100 border border-gray-100 rounded-lg">
                        @foreach($float['exempt_businesses'] as $row)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm">
                                <a href="{{ route('admin.businesses.show', $row['id']) }}" class="text-primary hover:underline">
                                    #{{ $row['id'] }} · {{ $row['name'] }}
                                </a>
                                <span class="font-mono text-gray-800">{{ $fmt($row['balance']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(count($float['exempt_wallets']) > 0)
                <div>
                    <p class="text-xs font-medium text-gray-600 mb-2">Wallets</p>
                    <ul class="divide-y divide-gray-100 border border-gray-100 rounded-lg">
                        @foreach($float['exempt_wallets'] as $row)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm">
                                <a href="{{ route('admin.whatsapp-wallet.wallets.show', $row['id']) }}" class="text-primary hover:underline font-mono">
                                    {{ $row['phone'] }}
                                    @if($row['name'])
                                        <span class="text-gray-500 font-sans">· {{ $row['name'] }}</span>
                                    @endif
                                </a>
                                <span class="font-mono text-gray-800">{{ $fmt($row['liability']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    <div>
        <h2 class="text-lg font-semibold text-gray-900">Payment provider audits</h2>
        <p class="text-sm text-gray-600 mt-1">
            Fee ledgers and reconciliation for payment APIs. Open a provider to review transactions and export reports.
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($providers as $provider)
            <a href="{{ route($provider['route']) }}"
               class="block bg-white rounded-lg border border-gray-200 p-5 shadow-sm hover:border-indigo-300 hover:shadow-md transition-shadow">
                <div class="flex items-start gap-3">
                    <span class="flex-shrink-0 w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <i class="fas {{ $provider['icon'] }}"></i>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-gray-900">{{ $provider['name'] }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ $provider['description'] }}</p>
                        <span class="mt-3 inline-flex items-center text-sm font-medium text-indigo-600">
                            Open audit
                            <i class="fas fa-arrow-right ml-1 text-xs"></i>
                        </span>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endsection

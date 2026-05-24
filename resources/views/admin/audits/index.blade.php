@extends('layouts.admin')

@section('title', 'Audits')
@section('page-title', 'Payment provider audits')

@section('content')
<div class="space-y-6">
    <p class="text-sm text-gray-600">
        Fee ledgers and reconciliation for payment APIs. Open a provider to review transactions and export reports.
    </p>

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

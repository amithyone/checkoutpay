@extends('layouts.business')

@section('title', 'Lending offers')
@section('page-title', 'My lending offers')

@section('content')
<div class="max-w-4xl mx-auto space-y-4">
    @if(session('success'))<div class="p-3 bg-green-50 border border-green-200 text-green-800 rounded text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="p-3 bg-red-50 border border-red-200 text-red-800 rounded text-sm">{{ session('error') }}</div>@endif

    <div class="flex justify-between items-center">
        <p class="text-sm text-gray-600">Funds are held from your balance only after a loan is disbursed.</p>
        @if(auth('business')->user()->peer_lending_lend_eligible)
            <a href="{{ route('business.lending-offers.create') }}" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium">New offer</a>
        @endif
    </div>

    @if(!auth('business')->user()->peer_lending_lend_eligible)
        <div class="p-4 bg-amber-50 border border-amber-200 text-amber-900 rounded-lg text-sm">Your business is not approved to publish lending offers. Ask an administrator to enable “Peer lend” on your account.</div>
    @endif

    <div class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">
        @forelse($offers as $o)
            <div class="p-4 flex flex-wrap justify-between gap-2 items-center">
                <div>
                    <p class="font-semibold text-gray-900">₦{{ number_format($o->amount, 2) }} · {{ number_format($o->interest_rate_percent, 2) }}%</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $o->term_days }} days · {{ $o->repayment_type }} · {{ $o->status }}</p>
                    <p class="text-xs text-gray-400 mt-1">Public: /business-loans/{{ $o->public_slug }}</p>
                </div>
                <div class="flex gap-2">
                    @if($o->status === \App\Models\BusinessLendingOffer::STATUS_ACTIVE)
                        <form action="{{ route('business.lending-offers.pause', $o) }}" method="POST">@csrf<button class="text-xs px-2 py-1 border rounded">Pause</button></form>
                    @elseif($o->status === \App\Models\BusinessLendingOffer::STATUS_PAUSED)
                        <form action="{{ route('business.lending-offers.resume', $o) }}" method="POST">@csrf<button class="text-xs px-2 py-1 border rounded">Resume</button></form>
                    @endif
                    @if(in_array($o->status, [\App\Models\BusinessLendingOffer::STATUS_ACTIVE, \App\Models\BusinessLendingOffer::STATUS_PAUSED, \App\Models\BusinessLendingOffer::STATUS_PENDING_ADMIN]))
                        <form action="{{ route('business.lending-offers.close', $o) }}" method="POST" onsubmit="return confirm('Close this offer?');">@csrf<button class="text-xs px-2 py-1 bg-red-50 text-red-800 rounded">Close</button></form>
                    @endif
                </div>
            </div>
        @empty
            <p class="p-6 text-sm text-gray-600">No offers yet.</p>
        @endforelse
    </div>
    <div>{{ $offers->links() }}</div>
</div>
@endsection

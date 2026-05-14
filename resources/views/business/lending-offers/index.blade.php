@extends('layouts.business')

@section('title', 'Lending offers')
@section('page-title', 'My lending offers')

@section('content')
<div class="max-w-4xl mx-auto space-y-4">
    @if(session('success'))<div class="p-3 bg-green-50 border border-green-200 text-green-800 rounded text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="p-3 bg-red-50 border border-red-200 text-red-800 rounded text-sm">{{ session('error') }}</div>@endif

    @include('partials.peer-lending-interest-explainer', ['variant' => 'panel'])

    @if(auth('business')->user()->peer_lending_lend_eligible)
        @php $lenderRules = auth('business')->user()->peerLendingLenderRulesSummary(); @endphp
        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-900">
            <p class="font-semibold mb-1">Your lender limits (set by admin)</p>
            <p class="text-xs">Max offer now: ₦{{ number_format($lenderRules['max_amount'], 2) }} · Interest cap (of principal per term): {{ number_format($lenderRules['max_interest'], 2) }}% · Terms {{ $lenderRules['min_term'] }}–{{ $lenderRules['max_term'] }} days @if($lenderRules['reserve'] > 0) · Reserve ₦{{ number_format($lenderRules['reserve'], 2) }} @endif</p>
            @if(!empty($lenderRules['conditions']))
                <p class="text-xs mt-2 whitespace-pre-wrap border-t border-blue-200 pt-2">{{ $lenderRules['conditions'] }}</p>
            @endif
        </div>
    @endif

    <div class="flex justify-between items-center">
        <p class="text-sm text-gray-600">Funds are held from your balance only after a loan is disbursed.</p>
        @if(auth('business')->user()->peer_lending_lend_eligible)
            <a href="{{ route('business.lending-offers.create') }}" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium">New offer</a>
        @endif
    </div>

    @if(!auth('business')->user()->peer_lending_lend_eligible)
        <div class="p-4 bg-amber-50 border border-amber-200 text-amber-900 rounded-lg text-sm">Your business is not approved to publish lending offers. Ask an administrator to enable “Peer lend” on your account.</div>
    @endif

    <div class="bg-white rounded-lg border border-gray-200">
        <div class="px-4 py-3 border-b">
            <h3 class="text-sm font-semibold text-gray-800">Active loans (you are lender)</h3>
        </div>
        @forelse($activeLoans as $loan)
            @php
                $p = $loan->progressPercent();
                $sch = $loan->scheduleProgress();
            @endphp
            <div class="p-4 border-b last:border-b-0">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-medium text-gray-900">{{ $loan->borrower->name }}</p>
                    <p class="text-xs text-gray-500">Principal ₦{{ number_format($loan->principal, 2) }} · Repay ₦{{ number_format($loan->total_repayment, 2) }}</p>
                    <p class="text-xs text-gray-400">{{ number_format($loan->offer->interest_rate_percent, 2) }}% of principal · {{ $loan->offer->term_days }}d offer</p>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                    <div class="bg-green-600 h-2.5 rounded-full" style="width: {{ $p }}%"></div>
                </div>
                <p class="text-xs text-gray-600 mt-1">
                    {{ number_format($p, 1) }}% repaid · ₦{{ number_format($loan->repaidAmount(), 2) }} / ₦{{ number_format($loan->total_repayment, 2) }}
                    · Outstanding ₦{{ number_format($loan->outstandingAmount(), 2) }}
                    @if(($sch['total'] ?? 0) > 0)
                        · {{ $sch['paid'] }}/{{ $sch['total'] }} schedules paid
                    @endif
                </p>
                @include('partials.peer-loan-next-collection', ['loan' => $loan])
            </div>
        @empty
            <p class="p-6 text-sm text-gray-600">No active disbursed loans yet.</p>
        @endforelse
    </div>

    <div class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">
        @forelse($offers as $o)
            @php
                $blocking = [\App\Models\BusinessLoan::STATUS_PENDING_ADMIN, \App\Models\BusinessLoan::STATUS_ACTIVE, \App\Models\BusinessLoan::STATUS_REPAID, \App\Models\BusinessLoan::STATUS_DEFAULTED];
                $canEdit = ! $o->loans()->whereIn('status', $blocking)->exists();
            @endphp
            <div class="p-4 flex flex-wrap justify-between gap-2 items-center">
                <div>
                    <p class="font-semibold text-gray-900">₦{{ number_format($o->amount, 2) }} · {{ number_format($o->interest_rate_percent, 2) }}% of principal</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $o->term_days }} days (due dates only) · {{ $o->repayment_type === 'lump' ? 'One-time' : 'Split ('.$o->repayment_frequency.')' }} · {{ $o->status }}</p>
                    <p class="text-xs text-gray-400 mt-1">Public: /business-loans/{{ $o->public_slug }}</p>
                </div>
                <div class="flex gap-2 flex-wrap">
                    @if($canEdit)
                        <a href="{{ route('business.lending-offers.edit', $o) }}" class="text-xs px-2 py-1 border rounded text-gray-700 hover:bg-gray-50">Edit</a>
                    @endif
                    @if($o->status === \App\Models\BusinessLendingOffer::STATUS_ACTIVE)
                        <form action="{{ route('business.lending-offers.pause', $o) }}" method="POST">@csrf<button class="text-xs px-2 py-1 border rounded">Pause</button></form>
                    @elseif($o->status === \App\Models\BusinessLendingOffer::STATUS_PAUSED)
                        <form action="{{ route('business.lending-offers.resume', $o) }}" method="POST">@csrf<button class="text-xs px-2 py-1 border rounded">Resume</button></form>
                    @endif
                    @if(in_array($o->status, [\App\Models\BusinessLendingOffer::STATUS_ACTIVE, \App\Models\BusinessLendingOffer::STATUS_PAUSED, \App\Models\BusinessLendingOffer::STATUS_PENDING_ADMIN]))
                        <form action="{{ route('business.lending-offers.close', $o) }}" method="POST" onsubmit="return confirm('Close this offer?');">@csrf<button class="text-xs px-2 py-1 bg-red-50 text-red-800 rounded">Close</button></form>
                    @endif
                    @if($canEdit)
                        <form action="{{ route('business.lending-offers.destroy', $o) }}" method="POST" onsubmit="return confirm('Delete this offer? This cannot be undone.');">@csrf @method('DELETE')<button class="text-xs px-2 py-1 bg-red-600 text-white rounded">Delete</button></form>
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

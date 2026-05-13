@extends('layouts.business')

@section('title', 'My loans')
@section('page-title', 'My loan applications')

@section('content')
<div class="max-w-4xl mx-auto space-y-4">
    @if(session('success'))<div class="p-3 bg-green-50 border border-green-200 text-green-800 rounded text-sm">{{ session('success') }}</div>@endif
    <p class="text-sm text-gray-600"><a href="{{ route('peer-loans.index') }}" class="text-primary hover:underline" target="_blank">Browse marketplace</a></p>
    <div class="bg-white rounded-lg border border-gray-200 divide-y">
        @forelse($loans as $loan)
            @php
                $p = $loan->progressPercent();
                $sch = $loan->scheduleProgress();
            @endphp
            <div class="p-4">
                <div class="flex flex-wrap justify-between gap-2">
                    <div>
                        <p class="font-medium text-gray-900">Lender: {{ $loan->offer->lender->name }}</p>
                        <p class="text-sm text-gray-600">Principal ₦{{ number_format($loan->principal, 2) }} → repay ₦{{ number_format($loan->total_repayment, 2) }}</p>
                        <p class="text-xs text-amber-700 mt-1">Status: {{ $loan->status }}</p>
                        @if($loan->status === \App\Models\BusinessLoan::STATUS_ACTIVE || $loan->status === \App\Models\BusinessLoan::STATUS_REPAID)
                            <div class="w-full max-w-md bg-gray-200 rounded-full h-2.5 mt-2">
                                <div class="bg-green-600 h-2.5 rounded-full" style="width: {{ $p }}%"></div>
                            </div>
                            <p class="text-xs text-gray-600 mt-1">
                                {{ number_format($p, 1) }}% repaid · ₦{{ number_format($loan->repaidAmount(), 2) }} / ₦{{ number_format($loan->total_repayment, 2) }}
                                · Outstanding ₦{{ number_format($loan->outstandingAmount(), 2) }}
                                @if(($sch['total'] ?? 0) > 0)
                                    · {{ $sch['paid'] }}/{{ $sch['total'] }} schedules paid
                                @endif
                            </p>
                        @endif
                    </div>
                </div>
                @if($loan->schedules->isNotEmpty())
                    <ul class="mt-3 text-xs text-gray-600 space-y-1">
                        @foreach($loan->schedules as $s)
                            <li>Due {{ $s->due_at->format('M d, Y') }}: ₦{{ number_format($s->amount_due, 2) }} — {{ $s->status }} (paid ₦{{ number_format($s->amount_paid, 2) }})</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @empty
            <p class="p-6 text-sm text-gray-600">No applications yet.</p>
        @endforelse
    </div>
    <div>{{ $loans->links() }}</div>
</div>
@endsection

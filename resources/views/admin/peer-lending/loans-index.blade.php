@extends('layouts.admin')

@section('title', 'Peer loan dashboard')
@section('page-title', 'Peer lending — loans dashboard')

@section('content')
@if(session('success'))<div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded text-sm">{{ session('success') }}</div>@endif
@if(session('error'))<div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded text-sm">{{ session('error') }}</div>@endif
@include('partials.peer-lending-interest-explainer', ['variant' => 'panel'])
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
    <div class="px-4 py-3 border-b bg-gray-50">
        <h3 class="text-sm font-semibold text-gray-800">Active loans</h3>
    </div>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Borrower</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Lender</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Repayment progress</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Outstanding</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($activeLoans as $loan)
                @php
                    $p = $loan->progressPercent();
                    $sch = $loan->scheduleProgress();
                @endphp
                <tr>
                    <td class="px-4 py-3">
                        <span class="block">{{ $loan->borrower->name }}</span>
                        <span class="text-xs text-gray-500">{{ number_format($loan->offer->interest_rate_percent, 2) }}% of principal · {{ $loan->offer->term_days }}d offer</span>
                    </td>
                    <td class="px-4 py-3">{{ $loan->offer->lender->name }}</td>
                    <td class="px-4 py-3 min-w-[16rem]">
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-green-600 h-2.5 rounded-full" style="width: {{ $p }}%"></div>
                        </div>
                        <div class="text-xs text-gray-600 mt-1">
                            {{ number_format($p, 1) }}% · ₦{{ number_format($loan->repaidAmount(), 2) }} / ₦{{ number_format($loan->total_repayment, 2) }}
                            @if(($sch['total'] ?? 0) > 0)
                                · {{ $sch['paid'] }}/{{ $sch['total'] }} schedules paid
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3">₦{{ number_format($loan->outstandingAmount(), 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No active loans.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-4 py-3 border-b bg-gray-50">
        <h3 class="text-sm font-semibold text-gray-800">Pending loan approvals</h3>
    </div>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Borrower</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Lender / Offer</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Principal → Total repay</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($pendingLoans as $loan)
                <tr>
                    <td class="px-4 py-3">{{ $loan->borrower->name }}</td>
                    <td class="px-4 py-3">{{ $loan->offer->lender->name }} <span class="text-gray-400">#{{ $loan->offer->id }}</span></td>
                    <td class="px-4 py-3">
                        <span class="block">₦{{ number_format($loan->principal, 2) }} → ₦{{ number_format($loan->total_repayment, 2) }}</span>
                        <span class="text-xs text-gray-500">{{ number_format($loan->offer->interest_rate_percent, 2) }}% of principal · {{ $loan->offer->term_days }}d</span>
                    </td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <form action="{{ route('admin.peer-lending.loans.approve', $loan) }}" method="POST" class="inline">@csrf<button class="text-xs px-2 py-1 bg-green-600 text-white rounded">Disburse</button></form>
                        <form action="{{ route('admin.peer-lending.loans.reject', $loan) }}" method="POST" class="inline" onsubmit="return confirm('Reject?');">@csrf<button class="text-xs px-2 py-1 bg-red-100 text-red-800 rounded">Reject</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No pending loans.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t">{{ $pendingLoans->links() }}</div>
</div>
@endsection

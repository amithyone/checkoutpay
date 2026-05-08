@extends('layouts.admin')

@section('title', 'Peer lending offers')
@section('page-title', 'Peer lending — pending offers')

@section('content')
@if(session('success'))<div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded text-sm">{{ session('success') }}</div>@endif
@if(session('error'))<div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded text-sm">{{ session('error') }}</div>@endif
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Lender</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Amount</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Rate / Term</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($offers as $o)
                <tr>
                    <td class="px-4 py-3">{{ $o->lender->name }}</td>
                    <td class="px-4 py-3">₦{{ number_format($o->amount, 2) }}</td>
                    <td class="px-4 py-3">{{ number_format($o->interest_rate_percent, 2) }}% · {{ $o->term_days }}d · {{ $o->repayment_type === 'lump' ? 'One-time' : 'Split ('.($o->repayment_frequency ?? 'weekly').')' }}</td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <form action="{{ route('admin.peer-lending.offers.approve', $o) }}" method="POST" class="inline">@csrf<button class="text-xs px-2 py-1 bg-green-600 text-white rounded">Approve</button></form>
                        <form action="{{ route('admin.peer-lending.offers.reject', $o) }}" method="POST" class="inline" onsubmit="return confirm('Reject?');">@csrf<button class="text-xs px-2 py-1 bg-red-100 text-red-800 rounded">Reject</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No pending offers.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t">{{ $offers->links() }}</div>
</div>
@endsection

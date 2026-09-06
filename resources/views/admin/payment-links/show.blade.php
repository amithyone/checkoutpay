@extends('layouts.admin')

@section('title', $link->title)
@section('page-title', 'Payment link')

@section('content')
<div class="space-y-6">
    <div>
        <a href="{{ route('admin.payment-links.index') }}" class="text-sm text-gray-600">← All payment links</a>
        <h3 class="text-lg font-semibold text-gray-900 mt-2">{{ $link->title }}</h3>
        <p class="text-sm text-gray-600">{{ $link->business->name ?? '—' }} · {{ $link->isReusable() ? 'Reusable' : 'One-time' }} · {{ ucfirst($link->status) }}</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-2 text-sm">
        <p><span class="text-gray-500">Amount:</span> {{ $link->formattedAmount() }}</p>
        <p><span class="text-gray-500">Collected:</span> ₦{{ number_format($link->collected_amount, 2) }} ({{ $link->collected_count }} payments)</p>
        <p><span class="text-gray-500">Views:</span> {{ $link->view_count }}</p>
        <p class="break-all"><span class="text-gray-500">URL:</span> <a href="{{ $link->payment_url }}" class="text-primary" target="_blank">{{ $link->payment_url }}</a></p>
        @if($link->description)
            <p><span class="text-gray-500">Description:</span> {{ $link->description }}</p>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Transaction</th>
                    <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Payer</th>
                    <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($link->linkPayments as $row)
                    <tr>
                        <td class="px-4 py-3 text-sm font-mono">{{ $row->payment->transaction_id ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $row->payment->payer_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">₦{{ number_format($row->amount, 2) }}</td>
                        <td class="px-4 py-3 text-sm">{{ $row->payment->status ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($row->payment)
                                <a href="{{ route('admin.payments.show', $row->payment) }}" class="text-primary text-sm">Payment</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No payments on this link.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

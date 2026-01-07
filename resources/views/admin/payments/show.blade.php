@extends('layouts.admin')

@section('title', 'Payment Details')
@section('page-title', 'Payment Details')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">Transaction: {{ $payment->transaction_id }}</h3>
            @if($payment->status === 'approved')
                <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
            @elseif($payment->status === 'pending')
                <span class="px-3 py-1 text-sm font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
            @else
                <span class="px-3 py-1 text-sm font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-6">
            <div>
                <label class="text-sm text-gray-600">Amount</label>
                <p class="text-lg font-bold text-gray-900">₦{{ number_format($payment->amount, 2) }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Business</label>
                <p class="text-sm font-medium text-gray-900">{{ $payment->business->name ?? 'N/A' }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Payer Name</label>
                <p class="text-sm font-medium text-gray-900">{{ $payment->payer_name ?? 'N/A' }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Bank</label>
                <p class="text-sm font-medium text-gray-900">{{ $payment->bank ?? 'N/A' }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Account Number</label>
                <p class="text-sm font-medium text-gray-900">{{ $payment->account_number ?? 'N/A' }}</p>
            </div>
            @if($payment->accountNumberDetails)
            <div>
                <label class="text-sm text-gray-600">Account Details</label>
                <p class="text-sm font-medium text-gray-900">
                    {{ $payment->accountNumberDetails->account_name }} - {{ $payment->accountNumberDetails->bank_name }}
                </p>
            </div>
            @endif
            <div>
                <label class="text-sm text-gray-600">Created At</label>
                <p class="text-sm font-medium text-gray-900">{{ $payment->created_at->format('M d, Y H:i:s') }}</p>
            </div>
            @if($payment->matched_at)
            <div>
                <label class="text-sm text-gray-600">Matched At</label>
                <p class="text-sm font-medium text-gray-900">{{ $payment->matched_at->format('M d, Y H:i:s') }}</p>
            </div>
            @endif
            @if($payment->expires_at)
            <div>
                <label class="text-sm text-gray-600">Expires At</label>
                <p class="text-sm font-medium text-gray-900">{{ $payment->expires_at->format('M d, Y H:i:s') }}</p>
            </div>
            @endif
        </div>

        @if($payment->email_data)
        <div class="mt-6 pt-6 border-t border-gray-200">
            <label class="text-sm text-gray-600 mb-2 block">Email Data</label>
            <pre class="bg-gray-50 p-4 rounded-lg text-xs overflow-x-auto">{{ json_encode($payment->email_data, JSON_PRETTY_PRINT) }}</pre>
        </div>
        @endif
    </div>
</div>
@endsection

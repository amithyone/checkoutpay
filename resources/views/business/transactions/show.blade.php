@extends('layouts.business')

@section('title', 'Transaction Details')
@section('page-title', 'Transaction Details')

@section('content')
<div class="space-y-4 sm:space-y-6 pb-20 sm:pb-0">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Transaction Information</h3>
            <div>
                @if($transaction->status === 'approved')
                    <span class="px-3 py-1 text-xs sm:text-sm font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                @elseif($transaction->status === 'pending')
                    <span class="px-3 py-1 text-xs sm:text-sm font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                @else
                    <span class="px-3 py-1 text-xs sm:text-sm font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Transaction ID</label>
                <p class="text-xs sm:text-sm text-gray-900 font-mono break-all">{{ $transaction->transaction_id }}</p>
            </div>
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Amount</label>
                <p class="text-base sm:text-lg text-gray-900 font-semibold">₦{{ number_format($transaction->amount, 2) }}</p>
            </div>
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Payer Name</label>
                <p class="text-xs sm:text-sm text-gray-900 break-words">{{ $transaction->payer_name ?? '-' }}</p>
            </div>
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Bank</label>
                <p class="text-xs sm:text-sm text-gray-900">{{ $transaction->bank ?? '-' }}</p>
            </div>
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Account Number</label>
                <p class="text-xs sm:text-sm text-gray-900 font-mono">{{ $transaction->account_number ?? '-' }}</p>
            </div>
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Website</label>
                <p class="text-xs sm:text-sm text-gray-900">
                    @if($transaction->website)
                        <a href="{{ $transaction->website->website_url }}" target="_blank" class="text-primary hover:underline break-all">
                            {{ Str::limit($transaction->website->website_url, 40) }}
                            <i class="fas fa-external-link-alt text-xs ml-1"></i>
                        </a>
                    @else
                        <span class="text-gray-400">Not specified</span>
                    @endif
                </p>
            </div>
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Created At</label>
                <p class="text-xs sm:text-sm text-gray-900">{{ $transaction->created_at->format('M d, Y H:i:s') }}</p>
            </div>
            @if($transaction->matched_at)
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Verified At</label>
                <p class="text-xs sm:text-sm text-gray-900">{{ $transaction->matched_at->format('M d, Y H:i:s') }}</p>
            </div>
            @endif
            @if($transaction->expires_at)
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Expires At</label>
                <p class="text-xs sm:text-sm text-gray-900">{{ $transaction->expires_at->format('M d, Y H:i:s') }}</p>
            </div>
            @endif
        </div>

        <div class="mt-6 pt-6 border-t border-gray-200">
            <a href="{{ route('business.transactions.index') }}" class="inline-flex items-center text-primary hover:underline text-sm">
                <i class="fas fa-arrow-left mr-2"></i> Back to Transactions
            </a>
        </div>
    </div>
</div>
@endsection

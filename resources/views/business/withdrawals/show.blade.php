@extends('layouts.business')

@section('title', 'Withdrawal Details')
@section('page-title', 'Withdrawal Details')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4 sm:mb-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Withdrawal Information</h3>
            <div>
                @if($withdrawal->status === 'approved')
                    <span class="px-3 py-1 text-xs sm:text-sm font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                @elseif($withdrawal->status === 'pending')
                    <span class="px-3 py-1 text-xs sm:text-sm font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                @elseif($withdrawal->status === 'rejected')
                    <span class="px-3 py-1 text-xs sm:text-sm font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                @else
                    <span class="px-3 py-1 text-xs sm:text-sm font-medium bg-blue-100 text-blue-800 rounded-full">Processed</span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                <p class="text-sm text-gray-900 font-semibold">₦{{ number_format($withdrawal->amount, 2) }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                <p class="text-sm text-gray-900">{{ $withdrawal->bank_name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
                <p class="text-sm text-gray-900 font-mono">{{ $withdrawal->account_number }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
                <p class="text-sm text-gray-900">{{ $withdrawal->account_name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Requested At</label>
                <p class="text-sm text-gray-900">{{ $withdrawal->created_at->format('M d, Y H:i:s') }}</p>
            </div>
            @if($withdrawal->processed_at)
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Processed At</label>
                <p class="text-sm text-gray-900">{{ $withdrawal->processed_at->format('M d, Y H:i:s') }}</p>
            </div>
            @endif
            @if($withdrawal->rejection_reason)
            <div class="sm:col-span-2">
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Rejection Reason</label>
                <p class="text-xs sm:text-sm text-red-600 break-words">{{ $withdrawal->rejection_reason }}</p>
            </div>
            @endif
            @if($withdrawal->notes)
            <div class="sm:col-span-2">
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Notes</label>
                <p class="text-xs sm:text-sm text-gray-900 break-words">{{ $withdrawal->notes }}</p>
            </div>
            @endif
        </div>

        <div class="mt-4 sm:mt-6 pt-4 sm:pt-6 border-t border-gray-200">
            <a href="{{ route('business.withdrawals.index') }}" class="inline-flex items-center text-xs sm:text-sm text-primary hover:underline">
                <i class="fas fa-arrow-left mr-2"></i> Back to Withdrawals
            </a>
        </div>
    </div>
</div>
@endsection

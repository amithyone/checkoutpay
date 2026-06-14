@extends('layouts.business')

@section('title', 'Loan Repayment')
@section('page-title', 'Loan Repayment')

@section('content')
<div class="space-y-4 sm:space-y-6 pb-20 sm:pb-0">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Loan Repayment</h3>
            <span class="px-3 py-1 text-xs sm:text-sm font-medium bg-green-100 text-green-800 rounded-full">Completed</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Reference</label>
                <p class="text-xs sm:text-sm text-gray-900 font-mono break-all">{{ $transaction->reference }}</p>
            </div>
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Amount</label>
                <p class="text-base sm:text-lg font-semibold {{ $transaction->isLoanRepaymentOut() ? 'text-red-700' : 'text-green-700' }}">
                    @if($transaction->isLoanRepaymentOut())−@else+@endif₦{{ number_format($transaction->amount, 2) }}
                </p>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Description</label>
                <p class="text-xs sm:text-sm text-gray-900">{{ $transaction->description ?? '—' }}</p>
            </div>
            @if($transaction->counterparty)
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">
                    {{ $transaction->isLoanRepaymentOut() ? 'Paid to' : 'Received from' }}
                </label>
                <p class="text-xs sm:text-sm text-gray-900">{{ $transaction->counterparty->name ?? ('Business #'.$transaction->counterparty->id) }}</p>
            </div>
            @endif
            @if($transaction->loanLedgerEntry?->loan)
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Loan ID</label>
                <p class="text-xs sm:text-sm text-gray-900">#{{ $transaction->loanLedgerEntry->loan->id }}</p>
            </div>
            @endif
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Date</label>
                <p class="text-xs sm:text-sm text-gray-900">{{ ($transaction->transaction_date ?? $transaction->created_at)->format('M d, Y H:i:s') }}</p>
            </div>
        </div>

        <div class="mt-6 pt-6 border-t border-gray-200">
            <a href="{{ route('business.transactions.index') }}" class="inline-flex items-center text-primary hover:underline text-sm">
                <i class="fas fa-arrow-left mr-2"></i> Back to Transactions
            </a>
        </div>
    </div>
</div>
@endsection

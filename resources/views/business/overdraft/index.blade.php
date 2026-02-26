@extends('layouts.business')

@section('title', 'Overdraft')
@section('page-title', 'Overdraft')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-4">{{ session('success') }}</div>
    @endif
    @if(session('info'))
        <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg p-4">{{ session('info') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
        <h3 class="text-base sm:text-lg font-semibold text-gray-900">Overdraft facility</h3>
        <p class="text-sm text-gray-600 mt-1">With admin approval, you can withdraw more than your balance up to an agreed limit. Interest of 5% applies after one week on the overdrawn amount.</p>

        <div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
            <p class="text-sm font-medium text-gray-700 mb-2">Available tiers</p>
            <ul class="text-sm text-gray-600 space-y-1">
                <li>• ₦200,000</li>
                <li>• ₦500,000</li>
                <li>• ₦1,000,000</li>
            </ul>
            <p class="text-xs text-gray-500 mt-2">5% interest is charged weekly on the overdrawn amount after the first 7 days.</p>
        </div>

        <div class="mt-6 pt-4 border-t border-gray-200">
            <p class="text-sm font-medium text-gray-700 mb-2">Your status</p>
            @if($business->hasOverdraftApproved())
                <p class="text-green-700 font-medium">Approved</p>
                <p class="text-sm text-gray-600 mt-1">Limit: ₦{{ number_format($business->overdraft_limit, 2) }}</p>
                <p class="text-sm text-gray-600">Available to withdraw: ₦{{ number_format($business->getAvailableBalance(), 2) }}</p>
            @elseif($business->overdraft_status === 'pending')
                <p class="text-amber-700 font-medium">Application pending</p>
                <p class="text-sm text-gray-600 mt-1">We will review your request shortly.</p>
            @elseif($business->overdraft_status === 'rejected')
                <p class="text-gray-600">Previously rejected. You may apply again.</p>
                <form method="POST" action="{{ route('business.overdraft.store') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">Apply for overdraft</button>
                </form>
            @else
                <p class="text-gray-600">You have not applied for overdraft.</p>
                <form method="POST" action="{{ route('business.overdraft.store') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">Apply for overdraft</button>
                </form>
            @endif
        </div>
    </div>

    <p class="text-center">
        <a href="{{ route('business.dashboard') }}" class="text-primary hover:underline text-sm">Back to Dashboard</a>
    </p>
</div>
@endsection

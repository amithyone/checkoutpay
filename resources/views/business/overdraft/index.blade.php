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
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
        <h3 class="text-base sm:text-lg font-semibold text-gray-900">Overdraft facility</h3>
        <p class="text-sm text-gray-600 mt-1">With admin approval, you can withdraw more than your balance up to an agreed limit. Interest of 5% applies weekly on the overdrawn amount after the first 7 days.</p>

        <div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
            <p class="text-sm font-medium text-gray-700 mb-2">Available tiers</p>
            <ul class="text-sm text-gray-600 space-y-1">
                @foreach($tiers as $value => $label)
                    <li>• {{ $label }}</li>
                @endforeach
            </ul>
            <p class="text-xs text-gray-500 mt-2">If you choose split repayment, four weekly targets are recorded when you first use the facility; incoming payments reduce what you owe.</p>
        </div>

        <div class="mt-6 pt-4 border-t border-gray-200">
            <p class="text-sm font-medium text-gray-700 mb-2">Your status</p>
            @if($business->hasOverdraftApproved())
                <p class="text-green-700 font-medium">Approved</p>
                <p class="text-sm text-gray-600 mt-1">Limit: ₦{{ number_format($business->overdraft_limit, 2) }}</p>
                <p class="text-sm text-gray-600">Funding source (admin): {{ $business->overdraft_funding_source ?? 'platform' }}</p>
                <p class="text-sm text-gray-600">Repayment preference: {{ $business->overdraft_repayment_mode === 'split_30d' ? 'Split (~30 days)' : 'Single / as cashflow allows' }}</p>
                <p class="text-sm text-gray-600">Available to withdraw: ₦{{ number_format($business->getAvailableBalance(), 2) }}</p>
                @if($installments->isNotEmpty())
                    <div class="mt-4">
                        <p class="text-xs font-medium text-gray-700 mb-2">Installment targets</p>
                        <ul class="text-xs text-gray-600 space-y-1">
                            @foreach($installments as $row)
                                <li>#{{ $row->sequence }} due {{ $row->due_at->format('M d') }}: ₦{{ number_format($row->amount_due, 2) }} — <span class="font-medium">{{ $row->status }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @elseif($business->overdraft_status === 'pending')
                <p class="text-amber-700 font-medium">Application pending</p>
                <p class="text-sm text-gray-600 mt-1">We will review your request shortly.</p>
            @elseif(!$business->overdraft_eligible)
                <p class="text-gray-600">Your business is not yet eligible to apply for overdraft. Please contact support.</p>
            @elseif($business->overdraft_status === 'rejected')
                <p class="text-gray-600">Previously rejected. You may apply again.</p>
                <form method="POST" action="{{ route('business.overdraft.store') }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Repayment preference</label>
                        <select name="overdraft_repayment_mode" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                            <option value="single">Single / pay down as you receive payments</option>
                            <option value="split_30d">Split (~30 days, 4 weekly targets when you first use credit)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Notes (optional)</label>
                        <textarea name="overdraft_application_notes" rows="2" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('overdraft_application_notes') }}</textarea>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">Apply for overdraft</button>
                </form>
            @else
                <p class="text-gray-600">You have not applied for overdraft.</p>
                <form method="POST" action="{{ route('business.overdraft.store') }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Repayment preference</label>
                        <select name="overdraft_repayment_mode" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                            <option value="single">Single / pay down as you receive payments</option>
                            <option value="split_30d">Split (~30 days, 4 weekly targets when you first use credit)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Notes (optional)</label>
                        <textarea name="overdraft_application_notes" rows="2" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('overdraft_application_notes') }}</textarea>
                    </div>
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

@extends('layouts.account')
@section('title', 'Fund Wallet')
@section('page-title', 'Fund Wallet')
@section('content')
<div class="max-w-xl mx-auto">
    <p class="mb-4 text-gray-600">Add money to your wallet. After you transfer, your wallet will be credited once the payment is confirmed.</p>
    <form action="{{ route('user.wallet.store') }}" method="POST" class="rounded-2xl border border-gray-200 bg-white p-6">
        @csrf
        <div class="mb-4">
            <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount (₦) *</label>
            <input type="number" name="amount" id="amount" value="{{ old('amount') }}" min="1" step="0.01" required class="w-full rounded-lg border border-gray-300 px-3 py-2">
        </div>
        <div class="mb-4">
            <label for="name_on_transfer" class="block text-sm font-medium text-gray-700 mb-1">Name on transfer *</label>
            <input type="text" name="name_on_transfer" id="name_on_transfer" value="{{ old('name_on_transfer', $user->name ?? '') }}" required maxlength="255" class="w-full rounded-lg border border-gray-300 px-3 py-2">
        </div>
        <div class="flex gap-3">
            <button type="submit" class="rounded-xl bg-primary text-white px-4 py-3 font-semibold hover:opacity-90">Continue</button>
            <a href="{{ route('user.wallet') }}" class="rounded-xl border border-gray-300 px-4 py-3 font-medium text-gray-700">Cancel</a>
        </div>
    </form>
    @if(isset($accountNumber) && $accountNumber)
    <div class="mt-6 p-4 bg-gray-50 rounded-xl text-sm text-gray-600">
        <p class="font-medium text-gray-800">Payment details</p>
        <p>Account: {{ $accountNumber->account_number }} - {{ $accountNumber->account_name ?? '' }} - {{ $accountNumber->bank_name ?? '' }}</p>
    </div>
    @endif
</div>
@endsection

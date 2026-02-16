@extends('layouts.account')
@section('title', 'Wallet')
@section('page-title', 'Wallet')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="rounded-2xl px-4 py-6 mb-6 bg-blue-50 border-2 border-blue-200">
        <p class="text-sm font-semibold text-gray-700">Wallet balance</p>
        <p class="text-2xl font-bold text-primary">₦{{ number_format($user->wallet_bal ?? 0, 2) }}</p>
    </div>
    <a href="{{ route('user.wallet.fund') }}" class="inline-flex items-center gap-2 rounded-xl bg-primary text-white px-4 py-2.5 font-semibold">Fund wallet</a>
    <a href="{{ route('user.wallet.history') }}" class="block mt-4 rounded-2xl border-2 border-gray-200 bg-white px-4 py-4">View transaction history</a>
</div>
@endsection

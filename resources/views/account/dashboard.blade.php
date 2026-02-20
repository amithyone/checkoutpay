@extends('layouts.account')
@section('title', 'My Account')
@section('page-title', 'Dashboard')
@section('content')
<div class="max-w-4xl mx-auto pb-2">
    <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 mb-4">
        @if($showWelcomeBack ?? false)
        <div class="rounded-2xl bg-gray-100 px-4 py-4 flex-1 shadow-md">
            <h2 class="text-xl font-bold text-gray-800">Welcome back</h2>
            <p class="text-base text-gray-600 mt-0.5 break-all">{{ $user->email }}{{ $user->name ? ' · ' . $user->name : '' }}</p>
        </div>
        @endif
        <div class="rounded-2xl px-4 py-4 {{ ($showWelcomeBack ?? false) ? 'flex-shrink-0 min-w-[140px]' : 'flex-1' }} flex flex-col justify-center shadow-md {{ (float)($user->wallet_bal ?? 0) < 0 ? 'bg-red-50 border-2 border-red-200' : 'bg-blue-50 border-2 border-blue-200' }}">
            <p class="text-sm font-semibold {{ (float)($user->wallet_bal ?? 0) < 0 ? 'text-red-800' : 'text-gray-700' }}">Wallet balance</p>
            <p class="text-xl font-bold {{ (float)($user->wallet_bal ?? 0) < 0 ? 'text-red-600' : 'text-primary' }}">₦{{ number_format($user->wallet_bal ?? 0, 2) }}</p>
            @if((float)($user->wallet_bal ?? 0) < 0)<p class="text-xs text-red-600 mt-0.5">Includes penalties; settle to clear</p>@endif
            <a href="{{ route('user.wallet.fund') }}" class="mt-2 inline-block text-sm font-medium text-primary hover:underline">Fund balance</a>
        </div>
    </div>

    @if($user->hasBusinessProfile())
    <a href="{{ route('user.switch-to-business') }}" class="flex items-center justify-center w-full gap-2 rounded-2xl py-3.5 px-4 mb-6 font-semibold text-base bg-blue-100 text-blue-800 hover:opacity-90 shadow-md">
        <i class="fas fa-briefcase text-lg"></i><span>Open Business dashboard</span>
    </a>
    @endif

    @if(isset($user->penalty_balance) && (float)$user->penalty_balance > 0)
    <div class="rounded-2xl border-2 border-amber-200 bg-amber-50 px-4 py-3 mb-4 shadow-md">
        <p class="text-sm font-semibold text-amber-800">Outstanding penalty balance</p>
        <p class="text-lg font-bold text-amber-900">₦{{ number_format($user->penalty_balance, 2) }}</p>
    </div>
    @endif

    @if(isset($activeRentals) && $activeRentals->count() > 0)
    <div class="rounded-2xl bg-gray-100 px-4 py-4 mb-4 shadow-md">
        <h2 class="text-lg font-bold text-gray-800">Gear due back</h2>
        <ul class="mt-3 space-y-3">
            @foreach($activeRentals as $rental)
            @php $deadline = $rental->returnDeadline(); $overdue = $rental->isOverdue(); @endphp
            <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white px-3 py-2.5 border border-gray-200">
                <div>
                    <span class="font-medium text-gray-800">{{ $rental->rental_number }}</span>
                    @if($rental->business)<span class="text-gray-500 text-sm"> · {{ $rental->business->name }}</span>@endif
                    <p class="text-xs text-gray-500 mt-0.5">Return by {{ $deadline->format('M j, Y g:i A') }}</p>
                </div>
                <span class="inline-block px-2 py-1 rounded-lg text-sm font-semibold {{ $overdue ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' }}">{{ $overdue ? 'Overdue' : 'Due soon' }}</span>
            </li>
            @endforeach
        </ul>
        <a href="{{ route('user.purchases') }}" class="inline-block text-sm font-medium mt-2 text-primary">View all rentals →</a>
    </div>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:gap-4">
        <a href="{{ route('user.purchases') }}" class="dashboard-card p-4 flex flex-col justify-between block bg-gray-200 rounded-2xl shadow-md">
            <h3 class="font-semibold text-gray-800">Rentals</h3>
            <p class="text-gray-600 text-sm mt-1">{{ $recentRentals->count() }} in history</p>
            <span class="text-sm font-medium mt-2 text-gray-700">View →</span>
        </a>
        <a href="{{ route('user.invoices') }}" class="dashboard-card p-4 flex flex-col justify-between block bg-green-100 rounded-2xl shadow-md">
            <h3 class="font-semibold text-gray-800">Invoices</h3>
            <p class="text-gray-600 text-sm mt-1">{{ $pendingInvoices->count() }} to pay</p>
            <span class="text-sm font-medium mt-2 text-primary">View & pay →</span>
        </a>
        <a href="{{ route('user.purchases') }}" class="dashboard-card p-4 flex flex-col justify-between block bg-amber-100 rounded-2xl shadow-md">
            <h3 class="font-semibold text-gray-800">Tickets</h3>
            <p class="text-gray-600 text-sm mt-1">{{ $validTicketOrders->count() }} upcoming</p>
            <span class="text-sm font-medium mt-2 text-primary">View →</span>
        </a>
        <a href="{{ route('user.purchases') }}" class="dashboard-card p-4 flex flex-col justify-between block bg-green-50 rounded-2xl shadow-md">
            <h3 class="font-semibold text-gray-800">Membership</h3>
            <p class="text-gray-600 text-sm mt-1">{{ $activeMemberships->count() }} active</p>
            <span class="text-sm font-medium mt-2 text-primary">View →</span>
        </a>
        <a href="{{ route('user.reviews.index') }}" class="dashboard-card p-4 flex flex-col justify-between block bg-red-50 rounded-2xl shadow-md">
            <h3 class="font-semibold text-gray-800">Reviews</h3>
            <p class="text-gray-600 text-sm mt-1">Reviews</p>
            <span class="text-sm font-medium mt-2 text-primary">View →</span>
        </a>
        <a href="{{ route('user.profile') }}" class="dashboard-card p-4 flex flex-col justify-between block bg-teal-50 rounded-2xl shadow-md">
            <h3 class="font-semibold text-gray-800">Profile</h3>
            <p class="text-gray-700 text-sm mt-1">Info & settings</p>
            <span class="text-sm font-medium mt-2 text-gray-800">Open →</span>
        </a>
    </div>
</div>
@endsection

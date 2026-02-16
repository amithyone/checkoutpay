@extends('layouts.account')
@section('title', 'Wallet History')
@section('page-title', 'Wallet History')
@section('content')
<div class="max-w-2xl mx-auto">
    <a href="{{ route('user.wallet') }}" class="text-sm font-medium text-primary hover:underline mb-4 inline-block">← Back to wallet</a>
    <h2 class="text-lg font-bold text-gray-800 mb-4">Transaction history</h2>
    @if($transactions->isEmpty())
    <p class="text-gray-600">No transactions yet.</p>
    <a href="{{ route('user.wallet.fund') }}" class="inline-block mt-2 text-primary font-medium hover:underline">Fund your wallet</a>
    @else
    <ul class="space-y-3">
        @foreach($transactions as $tx)
        <li class="flex justify-between items-center py-3 border-b border-gray-200">
            <div>
                <p class="font-medium text-gray-800">{{ $tx->description }}</p>
                <p class="text-xs text-gray-500">{{ $tx->created_at->format('M j, Y g:i A') }}</p>
            </div>
            <span class="font-semibold {{ $tx->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $tx->amount >= 0 ? '+' : '' }}₦{{ number_format($tx->amount, 2) }}</span>
        </li>
        @endforeach
    </ul>
    {{ $transactions->links() }}
    @endif
</div>
@endsection

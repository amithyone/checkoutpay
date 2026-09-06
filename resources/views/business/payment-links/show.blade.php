@extends('layouts.business')

@section('title', $link->title)
@section('page-title', 'Payment link')

@section('content')
<div class="max-w-4xl space-y-6 pb-20 lg:pb-0">
    <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
        <div>
            <a href="{{ route('business.payment-links.index') }}" class="text-sm text-gray-600 hover:text-gray-900"><i class="fas fa-arrow-left mr-1"></i> All links</a>
            <h2 class="text-xl lg:text-2xl font-bold text-gray-900 mt-2">{{ $link->title }}</h2>
            <p class="text-sm text-gray-600">{{ $link->isReusable() ? 'Reusable' : 'One-time' }} · {{ $link->formattedAmount() }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($link->isActive())
                <form method="POST" action="{{ route('business.payment-links.pause', $link) }}">@csrf<button class="px-3 py-2 bg-yellow-50 text-yellow-800 rounded-lg text-sm">Pause</button></form>
            @elseif($link->status === 'paused')
                <form method="POST" action="{{ route('business.payment-links.resume', $link) }}">@csrf<button class="px-3 py-2 bg-green-50 text-green-800 rounded-lg text-sm">Resume</button></form>
            @endif
            <form method="POST" action="{{ route('business.payment-links.destroy', $link) }}" onsubmit="return confirm('Remove this payment link?')">
                @csrf @method('DELETE')
                <button class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">{{ $link->collected_count > 0 ? 'Cancel' : 'Delete' }}</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-1">Share this link</h3>
        <p class="text-sm text-gray-600 mb-3">Send it to your client so they can pay on a dedicated page.</p>
        <div class="flex flex-col sm:flex-row gap-2">
            <input id="pay-url" type="text" readonly value="{{ $link->payment_url }}" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono bg-gray-50">
            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('pay-url').value)" class="px-4 py-2 bg-primary text-white rounded-lg text-sm">Copy link</button>
            <a href="{{ $link->payment_url }}" target="_blank" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm text-center">Open</a>
        </div>
        <p class="text-xs text-gray-500 mt-2">Status: <span class="font-medium">{{ ucfirst($link->status) }}</span> · Views: {{ $link->view_count }} · Collected ₦{{ number_format($link->collected_amount, 2) }} ({{ $link->collected_count }})</p>
        @if($link->description)
            <p class="text-sm text-gray-700 mt-3">{{ $link->description }}</p>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <h3 class="text-sm font-semibold text-gray-900 px-4 pt-4">Payments on this link</h3>
        <table class="w-full mt-2">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Transaction</th>
                    <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Payer</th>
                    <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($link->linkPayments as $row)
                    <tr>
                        <td class="px-4 py-3 text-sm font-mono">{{ $row->payment->transaction_id ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $row->payment->payer_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">₦{{ number_format($row->amount, 2) }}</td>
                        <td class="px-4 py-3 text-sm">{{ $row->payment->status ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">No payments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

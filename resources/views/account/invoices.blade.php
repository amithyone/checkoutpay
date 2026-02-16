@extends('layouts.account')
@section('title', 'Invoices')
@section('page-title', 'Invoices')
@section('content')
<div class="max-w-4xl mx-auto">
    <a href="{{ route('user.dashboard') }}" class="text-sm font-medium text-primary hover:underline mb-4 inline-block">Back to dashboard</a>
    <h2 class="text-lg font-bold text-gray-800 mb-4">Your invoices</h2>
    @if($invoices->count() > 0)
    <ul class="space-y-3">
        @foreach($invoices as $invoice)
        <li class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <span class="font-medium text-gray-800">{{ $invoice->invoice_number }}</span>
                @if($invoice->business)<span class="text-gray-500 text-sm"> - {{ $invoice->business->name }}</span>@endif
                <p class="text-xs text-gray-500 mt-0.5">Due {{ $invoice->due_date?->format('M j, Y') }} - {{ $invoice->status }}</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium">₦{{ number_format($invoice->total_amount, 2) }}</span>
                @if(!in_array($invoice->status, ['paid', 'cancelled']) && $invoice->payment_link_code)
                <a href="{{ route('invoices.pay', $invoice->payment_link_code) }}" class="px-3 py-1.5 bg-primary text-white text-sm rounded-lg">Pay</a>
                @endif
                @if($invoice->payment_link_code)
                <a href="{{ route('invoices.view', $invoice->payment_link_code) }}" class="text-sm text-primary hover:underline">View</a>
                @endif
            </div>
        </li>
        @endforeach
    </ul>
    @else
    <p class="text-gray-600">No invoices.</p>
    @endif
</div>
@endsection

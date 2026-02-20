@extends('layouts.account')
@section('title', 'Invoices')
@section('page-title', 'Invoices')
@section('content')
@php $accentColor = \App\Models\Setting::get('rentals_accent_color', '#000000'); @endphp
<div class="max-w-4xl mx-auto pb-24 lg:pb-2">
    <a href="{{ route('user.dashboard') }}" class="text-sm font-medium hover:underline mb-4 inline-block" style="color: {{ $accentColor }};">← Back to dashboard</a>

    <section class="rounded-2xl bg-white border border-gray-200 shadow overflow-hidden">
        <div class="px-4 py-3 sm:px-5 sm:py-4 border-b border-gray-100 flex items-center gap-3" style="background-color: {{ $accentColor }}15;">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white flex-shrink-0" style="background-color: {{ $accentColor }};">
                <i class="fas fa-file-invoice"></i>
            </div>
            <h2 class="text-lg font-bold text-gray-800">Your invoices</h2>
        </div>
        <div class="p-4 sm:p-5">
            @if($invoices->count() > 0)
                <ul class="space-y-3">
                    @foreach($invoices as $invoice)
                        <li>
                            <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-3 sm:py-3.5 flex flex-wrap items-center justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-gray-900">{{ $invoice->invoice_number }}</p>
                                    @if($invoice->business)
                                        <p class="text-sm text-gray-500 mt-0.5">{{ $invoice->business->name }}</p>
                                    @endif
                                    <p class="text-xs text-gray-500 mt-0.5">Due {{ $invoice->due_date?->format('M j, Y') }} · {{ $invoice->status }}</p>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="font-semibold text-gray-800">₦{{ number_format($invoice->total_amount, 2) }}</span>
                                    @if(!in_array($invoice->status, ['paid', 'cancelled']) && $invoice->payment_link_code)
                                        <a href="{{ route('invoices.pay', $invoice->payment_link_code) }}" class="px-3 py-2 rounded-xl text-white text-sm font-medium hover:opacity-90" style="background-color: {{ $accentColor }};">Pay</a>
                                    @endif
                                    @if($invoice->payment_link_code)
                                        <a href="{{ route('invoices.view', $invoice->payment_link_code) }}" class="text-sm font-medium hover:underline" style="color: {{ $accentColor }};">View</a>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="text-center py-8 sm:py-10">
                    <i class="fas fa-file-invoice text-gray-300 text-4xl mb-4"></i>
                    <p class="text-gray-600">No invoices yet.</p>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection

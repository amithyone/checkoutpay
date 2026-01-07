@extends('layouts.admin')

@section('title', 'Email Details')
@section('page-title', 'Email Details')

@section('content')
<div class="space-y-6">
    <!-- Back Button -->
    <div>
        <a href="{{ route('admin.processed-emails.index') }}" class="text-primary hover:underline">
            <i class="fas fa-arrow-left mr-2"></i> Back to Inbox
        </a>
    </div>

    <!-- Email Details Card -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">
                        {{ $processedEmail->subject ?? 'No Subject' }}
                    </h2>
                    <div class="mt-2 flex items-center gap-4 text-sm text-gray-600">
                        <span>
                            <i class="fas fa-envelope mr-1"></i>
                            From: <strong>{{ $processedEmail->from_email }}</strong>
                        </span>
                        @if($processedEmail->from_name)
                            <span>{{ $processedEmail->from_name }}</span>
                        @endif
                    </div>
                </div>
                <div>
                    @if($processedEmail->is_matched)
                        <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">
                            <i class="fas fa-check-circle mr-1"></i> Matched
                        </span>
                    @else
                        <span class="px-3 py-1 text-sm font-medium bg-yellow-100 text-yellow-800 rounded-full">
                            <i class="fas fa-clock mr-1"></i> Unmatched
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <!-- Email Metadata -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="text-sm font-medium text-gray-500">Email Date</label>
                    <p class="mt-1 text-sm text-gray-900">
                        {{ $processedEmail->email_date ? $processedEmail->email_date->format('M d, Y H:i:s') : $processedEmail->created_at->format('M d, Y H:i:s') }}
                    </p>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-500">Email Account</label>
                    <p class="mt-1 text-sm text-gray-900">
                        @if($processedEmail->emailAccount)
                            {{ $processedEmail->emailAccount->email }}
                        @else
                            <span class="text-gray-400">N/A</span>
                        @endif
                    </p>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-500">Message ID</label>
                    <p class="mt-1 text-sm text-gray-900 font-mono text-xs break-all">
                        {{ $processedEmail->message_id }}
                    </p>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-500">Stored At</label>
                    <p class="mt-1 text-sm text-gray-900">
                        {{ $processedEmail->created_at->format('M d, Y H:i:s') }}
                    </p>
                </div>
            </div>

            <!-- Extracted Payment Info -->
            @if($processedEmail->amount || $processedEmail->sender_name || $processedEmail->account_number)
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Extracted Payment Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        @if($processedEmail->amount)
                            <div>
                                <label class="text-sm font-medium text-gray-500">Amount</label>
                                <p class="mt-1 text-lg font-bold text-gray-900">
                                    ₦{{ number_format($processedEmail->amount, 2) }}
                                </p>
                            </div>
                        @endif

                        @if($processedEmail->sender_name)
                            <div>
                                <label class="text-sm font-medium text-gray-500">Sender Name</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $processedEmail->sender_name }}</p>
                            </div>
                        @endif

                        @if($processedEmail->account_number)
                            <div>
                                <label class="text-sm font-medium text-gray-500">Account Number</label>
                                <p class="mt-1 text-sm text-gray-900 font-mono">{{ $processedEmail->account_number }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Matched Payment -->
            @if($processedEmail->is_matched && $processedEmail->matchedPayment)
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Matched Payment</h3>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Transaction ID</p>
                                <a href="{{ route('admin.payments.show', $processedEmail->matchedPayment) }}" 
                                    class="text-lg font-semibold text-primary hover:underline">
                                    {{ $processedEmail->matchedPayment->transaction_id }}
                                </a>
                                <p class="text-sm text-gray-600 mt-1">
                                    Matched at: {{ $processedEmail->matched_at->format('M d, Y H:i:s') }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-600">Amount</p>
                                <p class="text-lg font-bold text-gray-900">
                                    ₦{{ number_format($processedEmail->matchedPayment->amount, 2) }}
                                </p>
                                @if($processedEmail->matchedPayment->business)
                                    <p class="text-sm text-gray-600 mt-1">
                                        Business: {{ $processedEmail->matchedPayment->business->name }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Email Body -->
            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Email Content</h3>
                
                @if($processedEmail->html_body)
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <div class="prose max-w-none">
                            {!! $processedEmail->html_body !!}
                        </div>
                    </div>
                @elseif($processedEmail->text_body)
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <pre class="whitespace-pre-wrap text-sm text-gray-900 font-sans">{{ $processedEmail->text_body }}</pre>
                    </div>
                @else
                    <p class="text-sm text-gray-500">No email body content available</p>
                @endif
            </div>

            <!-- Raw Extracted Data -->
            @if($processedEmail->extracted_data)
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Raw Extracted Data</h3>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <pre class="text-xs text-gray-700 overflow-x-auto">{{ json_encode($processedEmail->extracted_data, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

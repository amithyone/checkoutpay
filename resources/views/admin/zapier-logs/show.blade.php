@extends('layouts.admin')

@section('title', 'Zapier Log Details')
@section('page-title', 'Zapier Log Details')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Zapier Payload Details</h3>
            <p class="text-sm text-gray-600 mt-1">Received: {{ $zapierLog->created_at->format('M d, Y H:i:s') }}</p>
        </div>
        <a href="{{ route('admin.zapier-logs.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
            <i class="fas fa-arrow-left mr-1"></i> Back to Logs
        </a>
    </div>

    <!-- Status Card -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h4 class="text-md font-semibold text-gray-900 mb-2">Status</h4>
                @if($zapierLog->status === 'matched')
                    <span class="px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-800">Matched</span>
                @elseif($zapierLog->status === 'processed')
                    <span class="px-3 py-1 text-sm font-medium rounded-full bg-blue-100 text-blue-800">Processed</span>
                @elseif($zapierLog->status === 'rejected')
                    <span class="px-3 py-1 text-sm font-medium rounded-full bg-yellow-100 text-yellow-800">Rejected</span>
                @elseif($zapierLog->status === 'error')
                    <span class="px-3 py-1 text-sm font-medium rounded-full bg-red-100 text-red-800">Error</span>
                @else
                    <span class="px-3 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-800">Received</span>
                @endif
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-600">IP Address</p>
                <p class="text-sm font-medium text-gray-900">{{ $zapierLog->ip_address ?? '-' }}</p>
            </div>
        </div>
        @if($zapierLog->status_message)
            <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-700"><strong>Message:</strong> {{ $zapierLog->status_message }}</p>
            </div>
        @endif
        @if($zapierLog->error_details)
            <div class="mt-4 p-3 bg-red-50 rounded-lg">
                <p class="text-sm text-red-800"><strong>Error Details:</strong></p>
                <pre class="text-xs text-red-700 mt-2 overflow-auto">{{ $zapierLog->error_details }}</pre>
            </div>
        @endif
    </div>

    <!-- Payload Information -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h4 class="text-md font-semibold text-gray-900 mb-4">Extracted Information</h4>
            <dl class="space-y-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Sender Email</dt>
                    <dd class="text-sm text-gray-900 mt-1">
                        <code class="bg-gray-100 px-2 py-1 rounded">{{ $zapierLog->extracted_from_email ?? '-' }}</code>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Sender Name</dt>
                    <dd class="text-sm text-gray-900 mt-1">{{ $zapierLog->sender_name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Amount</dt>
                    <dd class="text-sm text-gray-900 mt-1">
                        @if($zapierLog->amount)
                            ₦{{ number_format($zapierLog->amount, 2) }}
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Time Sent</dt>
                    <dd class="text-sm text-gray-900 mt-1">{{ $zapierLog->time_sent ?? '-' }}</dd>
                </div>
            </dl>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h4 class="text-md font-semibold text-gray-900 mb-4">Related Records</h4>
            <dl class="space-y-3">
                @if($zapierLog->payment)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Matched Payment</dt>
                        <dd class="text-sm text-gray-900 mt-1">
                            <a href="{{ route('admin.payments.show', $zapierLog->payment) }}" class="text-primary hover:underline">
                                {{ $zapierLog->payment->transaction_id }}
                            </a>
                        </dd>
                    </div>
                @endif
                @if($zapierLog->processedEmail)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Processed Email</dt>
                        <dd class="text-sm text-gray-900 mt-1">
                            <a href="{{ route('admin.processed-emails.show', $zapierLog->processedEmail) }}" class="text-primary hover:underline">
                                View Email #{{ $zapierLog->processedEmail->id }}
                            </a>
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    <!-- Full Payload -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h4 class="text-md font-semibold text-gray-900 mb-4">Full Payload from Zapier</h4>
        <pre class="bg-gray-50 p-4 rounded-lg overflow-auto text-xs"><code>{{ json_encode($zapierLog->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
    </div>

    <!-- Email Content -->
    @if($zapierLog->email_content)
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h4 class="text-md font-semibold text-gray-900 mb-4">Email Content</h4>
        <div class="bg-gray-50 p-4 rounded-lg">
            <div class="prose prose-sm max-w-none">
                {!! nl2br(e($zapierLog->email_content)) !!}
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

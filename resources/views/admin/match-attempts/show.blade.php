@extends('layouts.admin')

@section('title', 'Match Attempt Details')
@section('page-title', 'Match Attempt Details')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.match-attempts.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
            <i class="fas fa-arrow-left mr-1"></i> Back to Match Logs
        </a>
        <div class="flex gap-3">
            @if($matchAttempt->processedEmail && $matchAttempt->match_result === 'unmatched')
                <button onclick="reExtractAndMatch({{ $matchAttempt->processedEmail->id }})" 
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                    <i class="fas fa-sync-alt mr-2"></i> Re-extract & Match
                </button>
            @endif
            @if($matchAttempt->match_result === 'unmatched' && $matchAttempt->payment)
                <button onclick="retryMatch({{ $matchAttempt->id }})" 
                        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center">
                    <i class="fas fa-redo mr-2"></i> Retry Match
                </button>
            @endif
        </div>
    </div>

    <!-- Match Result Banner -->
    <div class="bg-white rounded-lg shadow-sm border-2 {{ $matchAttempt->match_result === 'matched' ? 'border-green-200 bg-green-50' : 'border-yellow-200 bg-yellow-50' }} p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                @if($matchAttempt->match_result === 'matched')
                    <i class="fas fa-check-circle text-green-600 text-3xl mr-4"></i>
                @else
                    <i class="fas fa-times-circle text-yellow-600 text-3xl mr-4"></i>
                @endif
                <div>
                    <h3 class="text-xl font-semibold {{ $matchAttempt->match_result === 'matched' ? 'text-green-900' : 'text-yellow-900' }}">
                        {{ ucfirst($matchAttempt->match_result) }}
                    </h3>
                    <p class="text-sm {{ $matchAttempt->match_result === 'matched' ? 'text-green-700' : 'text-yellow-700' }} mt-1">
                        Transaction ID: <strong>{{ $matchAttempt->transaction_id ?? '-' }}</strong>
                    </p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-600">Attempted at</p>
                <p class="text-sm font-medium text-gray-900">{{ $matchAttempt->created_at->format('M d, Y H:i:s') }}</p>
                @if($matchAttempt->processing_time_ms)
                    <p class="text-xs text-gray-500 mt-1">{{ number_format($matchAttempt->processing_time_ms, 2) }}ms</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Match Reason -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-info-circle text-primary mr-2"></i>Match Reason
        </h3>
        <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-sm text-gray-900 whitespace-pre-wrap font-mono">{{ $matchAttempt->reason }}</p>
        </div>
        @if($matchAttempt->details && isset($matchAttempt->details['extraction_failed']) && $matchAttempt->details['extraction_failed'])
        <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <h4 class="text-sm font-semibold text-yellow-900 mb-2">
                <i class="fas fa-exclamation-triangle mr-2"></i>Extraction Failed - Diagnostic Information Available
            </h4>
            <p class="text-xs text-yellow-700 mb-3">
                This match attempt failed because payment information could not be extracted from the email. 
                Check the detailed diagnostics below to identify the issue.
            </p>
            @if(isset($matchAttempt->details['extraction_steps']))
            <div class="mb-3">
                <h5 class="text-xs font-semibold text-yellow-900 mb-1">Extraction Steps Attempted:</h5>
                <ul class="text-xs text-yellow-800 list-disc list-inside">
                    @foreach($matchAttempt->details['extraction_steps'] as $step)
                    <li>{{ $step }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
            @if(isset($matchAttempt->details['extraction_errors']))
            <div class="mb-3">
                <h5 class="text-xs font-semibold text-yellow-900 mb-1">Errors Encountered:</h5>
                <ul class="text-xs text-yellow-800 list-disc list-inside">
                    @foreach($matchAttempt->details['extraction_errors'] as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
            @if(isset($matchAttempt->details['text_length']) || isset($matchAttempt->details['html_length']))
            <div class="mb-3">
                <h5 class="text-xs font-semibold text-yellow-900 mb-1">Content Lengths:</h5>
                <ul class="text-xs text-yellow-800 list-disc list-inside">
                    @if(isset($matchAttempt->details['text_length']))
                    <li>Text Body: {{ $matchAttempt->details['text_length'] }} chars</li>
                    @endif
                    @if(isset($matchAttempt->details['html_length']))
                    <li>HTML Body: {{ $matchAttempt->details['html_length'] }} chars</li>
                    @endif
                </ul>
            </div>
            @endif
            @if(isset($matchAttempt->details['text_preview']))
            <div class="mb-3">
                <h5 class="text-xs font-semibold text-yellow-900 mb-1">Text Body Preview (first 500 chars):</h5>
                <div class="bg-white rounded p-2 max-h-32 overflow-auto">
                    <pre class="text-xs text-gray-700 whitespace-pre-wrap font-mono">{{ $matchAttempt->details['text_preview'] }}</pre>
                </div>
            </div>
            @endif
            @if(isset($matchAttempt->details['html_preview']))
            <div class="mb-3">
                <h5 class="text-xs font-semibold text-yellow-900 mb-1">HTML Body Preview (first 500 chars):</h5>
                <div class="bg-white rounded p-2 max-h-32 overflow-auto">
                    <pre class="text-xs text-gray-700 whitespace-pre-wrap font-mono">{{ $matchAttempt->details['html_preview'] }}</pre>
                </div>
            </div>
            @endif
            @if($matchAttempt->processedEmail)
            <div class="mt-4 pt-3 border-t border-yellow-300">
                <a href="{{ route('admin.processed-emails.show', $matchAttempt->processedEmail) }}" 
                   class="text-xs text-yellow-900 hover:text-yellow-700 underline">
                    <i class="fas fa-envelope mr-1"></i> View Full Email Content (#{{ $matchAttempt->processedEmail->id }})
                </a>
            </div>
            @endif
        </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Payment Details -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-money-bill-wave text-primary mr-2"></i>Payment Details
            </h3>
            <dl class="space-y-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Transaction ID</dt>
                    <dd class="text-sm text-gray-900 mt-1">
                        @if($matchAttempt->payment)
                            <a href="{{ route('admin.payments.show', $matchAttempt->payment) }}" class="text-primary hover:underline">
                                {{ $matchAttempt->transaction_id ?? '-' }}
                            </a>
                        @else
                            {{ $matchAttempt->transaction_id ?? '-' }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Amount</dt>
                    <dd class="text-sm text-gray-900 mt-1">₦{{ number_format($matchAttempt->payment_amount ?? 0, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Payer Name</dt>
                    <dd class="text-sm text-gray-900 mt-1">{{ $matchAttempt->payment_name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Account Number</dt>
                    <dd class="text-sm text-gray-900 mt-1">{{ $matchAttempt->payment_account_number ?? '-' }}</dd>
                </div>
                @if($matchAttempt->payment_created_at)
                <div>
                    <dt class="text-sm font-medium text-gray-500">Created At</dt>
                    <dd class="text-sm text-gray-900 mt-1">{{ $matchAttempt->payment_created_at->format('M d, Y H:i:s') }}</dd>
                </div>
                @endif
            </dl>
        </div>

        <!-- Extracted Email Details -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-envelope text-primary mr-2"></i>Extracted Email Details
            </h3>
            <dl class="space-y-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Amount</dt>
                    <dd class="text-sm text-gray-900 mt-1">₦{{ number_format($matchAttempt->extracted_amount ?? 0, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Sender Name</dt>
                    <dd class="text-sm text-gray-900 mt-1">{{ $matchAttempt->extracted_name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Account Number</dt>
                    <dd class="text-sm text-gray-900 mt-1">{{ $matchAttempt->extracted_account_number ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Email Subject</dt>
                    <dd class="text-sm text-gray-900 mt-1">{{ $matchAttempt->email_subject ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Email From</dt>
                    <dd class="text-sm text-gray-900 mt-1">{{ $matchAttempt->email_from ?? '-' }}</dd>
                </div>
                @if($matchAttempt->email_date)
                <div>
                    <dt class="text-sm font-medium text-gray-500">Email Date</dt>
                    <dd class="text-sm text-gray-900 mt-1">{{ $matchAttempt->email_date->format('M d, Y H:i:s') }}</dd>
                </div>
                @endif
                @if($matchAttempt->processedEmail)
                <div>
                    <dt class="text-sm font-medium text-gray-500">Email Source</dt>
                    <dd class="text-sm text-gray-900 mt-1">
                        <a href="{{ route('admin.processed-emails.show', $matchAttempt->processedEmail) }}" class="text-primary hover:underline">
                            View Email #{{ $matchAttempt->processedEmail->id }}
                        </a>
                    </dd>
                </div>
                @endif
            </dl>
        </div>
    </div>

    <!-- Comparison Metrics -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-chart-line text-primary mr-2"></i>Comparison Metrics
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <dt class="text-sm font-medium text-gray-500 mb-2">Amount Difference</dt>
                <dd class="text-2xl font-bold {{ ($matchAttempt->amount_diff ?? 0) == 0 ? 'text-green-600' : (($matchAttempt->amount_diff ?? 0) > 0 ? 'text-yellow-600' : 'text-blue-600') }}">
                    ₦{{ number_format(abs($matchAttempt->amount_diff ?? 0), 2) }}
                    @if($matchAttempt->amount_diff > 0)
                        <span class="text-sm font-normal text-gray-600">(payment higher)</span>
                    @elseif($matchAttempt->amount_diff < 0)
                        <span class="text-sm font-normal text-gray-600">(email higher)</span>
                    @else
                        <span class="text-sm font-normal text-gray-600">(exact match)</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 mb-2">Name Similarity</dt>
                <dd class="text-2xl font-bold {{ ($matchAttempt->name_similarity_percent ?? 0) >= 65 ? 'text-green-600' : 'text-yellow-600' }}">
                    {{ $matchAttempt->name_similarity_percent ?? 0 }}%
                </dd>
                @if($matchAttempt->name_similarity_percent !== null)
                <div class="mt-2 w-full h-3 bg-gray-200 rounded-full overflow-hidden">
                    <div class="h-full {{ $matchAttempt->name_similarity_percent >= 65 ? 'bg-green-600' : 'bg-yellow-600' }}" 
                         style="width: {{ $matchAttempt->name_similarity_percent }}%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-1">Threshold: 65%</p>
                @endif
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 mb-2">Time Difference</dt>
                <dd class="text-2xl font-bold text-gray-900">
                    {{ $matchAttempt->time_diff_minutes ?? '-' }} {{ $matchAttempt->time_diff_minutes ? 'minutes' : '' }}
                </dd>
                @if($matchAttempt->time_diff_minutes !== null)
                    <p class="text-xs text-gray-500 mt-1">
                        @if($matchAttempt->time_diff_minutes < 0)
                            Email received before payment
                        @else
                            Email received {{ $matchAttempt->time_diff_minutes }} minutes after payment
                        @endif
                    </p>
                @endif
            </div>
        </div>
    </div>

    <!-- Extraction Method -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-code text-primary mr-2"></i>Extraction Method
        </h3>
        <div class="flex items-center gap-4">
            <span class="px-4 py-2 text-sm font-medium bg-blue-100 text-blue-800 rounded-lg">
                {{ $matchAttempt->extraction_method ?? 'unknown' }}
            </span>
            <div class="text-sm text-gray-600">
                @if($matchAttempt->extraction_method === 'html_table')
                    <i class="fas fa-check-circle text-green-600 mr-1"></i> Most accurate - HTML table extraction
                @elseif($matchAttempt->extraction_method === 'html_text')
                    <i class="fas fa-check text-blue-600 mr-1"></i> HTML text extraction
                @elseif($matchAttempt->extraction_method === 'rendered_text')
                    <i class="fas fa-check text-blue-600 mr-1"></i> Rendered text extraction
                @elseif($matchAttempt->extraction_method === 'template')
                    <i class="fas fa-star text-yellow-600 mr-1"></i> Bank template extraction
                @else
                    <i class="fas fa-info-circle text-gray-600 mr-1"></i> Fallback method
                @endif
            </div>
        </div>
    </div>

    <!-- Details JSON -->
    @if($matchAttempt->details)
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-database text-primary mr-2"></i>Full Comparison Details (JSON)
        </h3>
        <div class="bg-gray-50 rounded-lg p-4 overflow-auto max-h-96">
            <pre class="text-xs text-gray-900"><code>{{ json_encode($matchAttempt->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
        </div>
        <p class="text-xs text-gray-500 mt-2">
            <i class="fas fa-info-circle mr-1"></i> This JSON contains all the data used for matching comparison
        </p>
    </div>
    @endif

    <!-- HTML/Text Snippets -->
    @if($matchAttempt->html_snippet || $matchAttempt->text_snippet)
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @if($matchAttempt->html_snippet)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-code text-primary mr-2"></i>HTML Snippet (First 500 chars)
            </h3>
            <div class="bg-gray-50 rounded-lg p-4 overflow-auto max-h-64">
                <pre class="text-xs text-gray-900 whitespace-pre-wrap"><code>{{ $matchAttempt->html_snippet }}</code></pre>
            </div>
        </div>
        @endif

        @if($matchAttempt->text_snippet)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-file-alt text-primary mr-2"></i>Text Snippet (First 500 chars)
            </h3>
            <div class="bg-gray-50 rounded-lg p-4 overflow-auto max-h-64">
                <pre class="text-xs text-gray-900 whitespace-pre-wrap">{{ $matchAttempt->text_snippet }}</pre>
            </div>
        </div>
        @endif
    </div>
    @endif
</div>

<script>
function retryMatch(attemptId) {
    if (!confirm('Are you sure you want to retry matching this transaction?')) {
        return;
    }

    fetch(`/admin/match-attempts/${attemptId}/retry`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            if (data.payment) {
                window.location.href = '/admin/payments/' + data.payment.id;
            } else {
                window.location.reload();
            }
        } else {
            alert('❌ ' + data.message);
            if (data.latest_reason) {
                alert('Latest reason: ' + data.latest_reason);
            }
            if (data.latest_attempt) {
                console.log('Latest attempt:', data.latest_attempt);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error retrying match: ' + error.message);
    });
}

function reExtractAndMatch(processedEmailId) {
    if (!confirm('This will re-extract payment info from the email\'s text_body first, then html_body if needed, and try to match again. Continue?')) {
        return;
    }

    fetch(`/admin/processed-emails/${processedEmailId}/re-extract-match`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let message = '✅ ' + data.message;
            if (data.extraction_info) {
                message += '\n\nExtraction Details:';
                message += '\n- Text Body Used: ' + (data.extraction_info.text_body_used ? 'Yes' : 'No');
                message += '\n- HTML Body Used: ' + (data.extraction_info.html_body_used ? 'Yes' : 'No');
            }
            alert(message);
            if (data.payment) {
                window.location.href = '/admin/payments/' + data.payment.id;
            } else {
                window.location.reload();
            }
        } else {
            let message = '❌ ' + data.message;
            if (data.latest_reason) {
                message += '\n\nReason: ' + data.latest_reason;
            }
            if (data.latest_attempt) {
                message += '\n\nExtraction Method: ' + (data.latest_attempt.extraction_method || 'unknown');
            }
            if (data.email_info) {
                message += '\n\nEmail Info:';
                message += '\n- Text Body Length: ' + data.email_info.text_body_length + ' chars';
                message += '\n- HTML Body Length: ' + data.email_info.html_body_length + ' chars';
            }
            alert(message);
            if (data.latest_attempt) {
                console.log('Latest attempt:', data.latest_attempt);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error re-extracting and matching: ' + error.message);
    });
}
</script>
@endsection

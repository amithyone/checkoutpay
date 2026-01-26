@extends('layouts.admin')

@section('title', 'Email Details')
@section('page-title', 'Email Details')

@section('content')
<div class="space-y-6">
    <!-- Back Button and Actions -->
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.processed-emails.index') }}" class="text-primary hover:underline">
            <i class="fas fa-arrow-left mr-2"></i> Back to Inbox
        </a>
        @if(!$processedEmail->is_matched)
            <button onclick="retryEmailMatch({{ $processedEmail->id }})" 
                    class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center">
                <i class="fas fa-redo mr-2"></i> Retry Match
            </button>
        @endif
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
            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Extracted Payment Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Amount</label>
                        <div class="mt-1 flex items-center gap-2">
                            <div class="flex-1 relative">
                                <span class="absolute left-3 top-2 text-gray-500">₦</span>
                                <input type="number" 
                                       id="amount-input" 
                                       step="0.01"
                                       min="0"
                                       value="{{ $processedEmail->amount ?? '' }}" 
                                       class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm"
                                       placeholder="0.00">
                            </div>
                            <button onclick="updateAmount({{ $processedEmail->id }})" 
                                    class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                <i class="fas fa-save mr-1"></i> Update
                            </button>
                        </div>
                        @if(!$processedEmail->is_matched)
                            <button onclick="updateAmountAndRematch({{ $processedEmail->id }})" 
                                    class="mt-2 w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm flex items-center justify-center">
                                <i class="fas fa-redo mr-2"></i> Update Amount & Rematch
                            </button>
                        @endif
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-500">Sender Name</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input type="text" 
                                   id="sender-name-input" 
                                   value="{{ $processedEmail->sender_name ?? '' }}" 
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm"
                                   placeholder="Enter sender name">
                            <button onclick="updateSenderName({{ $processedEmail->id }})" 
                                    class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                <i class="fas fa-save mr-1"></i> Update
                            </button>
                        </div>
                        @if(!$processedEmail->is_matched)
                            <button onclick="updateAndRematch({{ $processedEmail->id }})" 
                                    class="mt-2 w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm flex items-center justify-center">
                                <i class="fas fa-redo mr-2"></i> Update Name & Rematch
                            </button>
                        @endif
                    </div>

                    @if($processedEmail->account_number)
                        <div>
                            <label class="text-sm font-medium text-gray-500">Account Number</label>
                            <p class="mt-1 text-sm text-gray-900 font-mono">{{ $processedEmail->account_number }}</p>
                        </div>
                    @else
                        <div>
                            <label class="text-sm font-medium text-gray-500">Account Number</label>
                            <p class="mt-1 text-sm text-gray-400">Not extracted</p>
                        </div>
                    @endif
                </div>
            </div>

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

    <!-- Match Attempts -->
    @if($processedEmail->last_match_reason || $processedEmail->match_attempts_count > 0)
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-search-dollar text-primary mr-2"></i>Match Attempts
        </h3>
        @if($processedEmail->last_match_reason)
        <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <p class="text-sm font-medium text-yellow-900 mb-1">Last Match Reason:</p>
            <p class="text-sm text-yellow-800">{{ $processedEmail->last_match_reason }}</p>
        </div>
        @endif
        @if($processedEmail->match_attempts_count > 0)
        <div class="text-sm text-gray-600">
            <i class="fas fa-info-circle mr-1"></i> This email has been attempted {{ $processedEmail->match_attempts_count }} time(s)
        </div>
        @endif
        <div class="mt-4">
            <a href="{{ route('admin.match-attempts.index', ['processed_email_id' => $processedEmail->id]) }}" 
               class="text-sm text-primary hover:underline">
                <i class="fas fa-list mr-1"></i> View All Match Attempts for This Email
            </a>
        </div>
    </div>
    @endif
</div>

<script>
function updateSenderName(emailId) {
    const input = document.getElementById('sender-name-input');
    const senderName = input.value.trim();
    
    if (!senderName) {
        alert('Please enter a sender name');
        return;
    }

    fetch(`/admin/processed-emails/${emailId}/update-name`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            sender_name: senderName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error updating name: ' + error.message);
    });
}

function updateAndRematch(emailId) {
    const input = document.getElementById('sender-name-input');
    const senderName = input.value.trim();
    
    // Allow empty - system will try to extract from text snippet
    if (!confirm('Update the sender name and retry matching this email against all pending payments?')) {
        return;
    }

    fetch(`/admin/processed-emails/${emailId}/update-and-rematch`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            sender_name: senderName || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            if (data.payment && data.redirect_url) {
                // Redirect to payment page if matched (payment will show as approved)
                window.location.href = data.redirect_url;
            } else {
                // Reload page to show updated status
                window.location.reload();
            }
        } else {
            alert('❌ ' + data.message);
            if (data.latest_reason) {
                console.log('Latest reason:', data.latest_reason);
                alert('Latest reason: ' + data.latest_reason);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error updating and rematching: ' + error.message);
    });
}

function updateAmount(emailId) {
    const input = document.getElementById('amount-input');
    const amount = parseFloat(input.value);
    
    if (!amount || amount <= 0) {
        alert('Please enter a valid amount greater than 0');
        return;
    }

    fetch(`/admin/processed-emails/${emailId}/update-amount`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            amount: amount
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error updating amount: ' + error.message);
    });
}

function updateAmountAndRematch(emailId) {
    const input = document.getElementById('amount-input');
    const amount = parseFloat(input.value);
    
    if (!amount || amount <= 0) {
        alert('Please enter a valid amount greater than 0 before rematching');
        return;
    }

    if (!confirm('Update the amount and retry matching this email against all pending payments?')) {
        return;
    }

    fetch(`/admin/processed-emails/${emailId}/update-amount-and-rematch`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            amount: amount
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            if (data.payment && data.redirect_url) {
                window.location.href = data.redirect_url;
            } else {
                window.location.reload();
            }
        } else {
            alert('❌ ' + data.message);
            if (data.latest_reason) {
                console.log('Latest reason:', data.latest_reason);
                alert('Latest reason: ' + data.latest_reason);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error updating amount and rematching: ' + error.message);
    });
}

function retryEmailMatch(emailId) {
    if (!confirm('Are you sure you want to retry matching this email against all pending payments?')) {
        return;
    }

    fetch(`/admin/processed-emails/${emailId}/retry-match`, {
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
                console.log('Latest reason:', data.latest_reason);
                alert('Latest reason: ' + data.latest_reason);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error retrying match: ' + error.message);
    });
}
</script>
@endsection

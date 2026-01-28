@extends('layouts.admin')

@section('title', 'Payment Details')
@section('page-title', 'Payment Details')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Transaction: {{ $payment->transaction_id }}</h3>
                @if($statusChecksCount >= 3)
                    <div class="mt-2 flex items-center space-x-2">
                        <span class="px-3 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Needs Review ({{ $statusChecksCount }} API checks)
                        </span>
                        <a href="{{ route('admin.payments.needs-review') }}" class="text-xs text-primary hover:underline">
                            View All Needing Review
                        </a>
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-3">
                @if($payment->status === 'pending')
                    <button onclick="checkMatchForPayment({{ $payment->id }})" 
                        id="check-match-btn"
                        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center check-match-payment-btn"
                        data-payment-id="{{ $payment->id }}">
                        <i class="fas fa-search mr-2"></i> Check Match
                    </button>
                    <button onclick="showManualVerifyModal({{ $payment->id }}, '{{ $payment->transaction_id }}', {{ $payment->amount }})" 
                        class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 flex items-center">
                        <i class="fas fa-check-double mr-2"></i> Manual Verify
                    </button>
                    <button onclick="showManualApproveModal({{ $payment->id }}, '{{ $payment->transaction_id }}', {{ $payment->amount }})" 
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                        <i class="fas fa-check-circle mr-2"></i> Manual Approve
                    </button>
                    @if(!$payment->expires_at || $payment->expires_at->isFuture())
                        <button onclick="markAsExpired({{ $payment->id }})" 
                            class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 flex items-center">
                            <i class="fas fa-clock mr-2"></i> Mark as Expired
                        </button>
                    @endif
                @endif
                <div class="relative group">
                    <button onclick="showDeleteModal({{ $payment->id }}, '{{ $payment->transaction_id }}')" 
                        class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center">
                        <i class="fas fa-trash mr-2"></i> Delete
                    </button>
                </div>
                @if($payment->status === 'approved')
                    <button onclick="resendWebhook({{ $payment->id }})" 
                        id="resend-webhook-btn"
                        class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 flex items-center"
                        title="Resend webhook notification to business">
                        <i class="fas fa-paper-plane mr-2"></i> Resend Webhook
                    </button>
                    <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                @elseif($payment->status === 'pending')
                    <span class="px-3 py-1 text-sm font-medium bg-yellow-100 text-yellow-800 rounded-full">
                        Pending
                        @if($payment->expires_at && $payment->expires_at->isPast())
                            <span class="ml-1 text-red-600">(Expired)</span>
                        @endif
                    </span>
                @else
                    <span class="px-3 py-1 text-sm font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6">
            <div>
                <label class="text-sm text-gray-600">Amount</label>
                <p class="text-lg font-bold text-gray-900">₦{{ number_format($payment->amount, 2) }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Business</label>
                <p class="text-sm font-medium text-gray-900">{{ $payment->business->name ?? 'N/A' }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Payer Name</label>
                <p class="text-sm font-medium text-gray-900">{{ $payment->payer_name ?? 'N/A' }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Bank</label>
                <p class="text-sm font-medium text-gray-900">{{ $payment->bank ?? 'N/A' }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Account Number</label>
                <p class="text-sm font-medium text-gray-900">{{ $payment->account_number ?? 'N/A' }}</p>
            </div>
            @if($payment->accountNumberDetails)
            <div>
                <label class="text-sm text-gray-600">Account Details</label>
                <p class="text-sm font-medium text-gray-900">
                    {{ $payment->accountNumberDetails->account_name }} - {{ $payment->accountNumberDetails->bank_name }}
                </p>
            </div>
            @endif
            <div>
                <label class="text-sm text-gray-600">Created At</label>
                <p class="text-sm font-medium text-gray-900">{{ $payment->created_at->format('M d, Y H:i:s') }}</p>
            </div>
            @if($payment->matched_at)
            <div>
                <label class="text-sm text-gray-600">Matched At</label>
                <p class="text-sm font-medium text-gray-900">{{ $payment->matched_at->format('M d, Y H:i:s') }}</p>
                @php
                    $matchingTimeMinutes = $payment->created_at->diffInMinutes($payment->matched_at);
                @endphp
                <p class="text-xs text-gray-500 mt-1">
                    <i class="fas fa-stopwatch mr-1"></i>
                    Matching Time: <span class="font-semibold text-teal-600">{{ $matchingTimeMinutes }} minutes</span>
                    ({{ $payment->created_at->diffForHumans($payment->matched_at, true) }})
                </p>
            </div>
            @elseif($payment->status === 'pending')
            <div>
                <label class="text-sm text-gray-600">Pending Time</label>
                @php
                    $pendingMinutes = $payment->created_at->diffInMinutes(now());
                @endphp
                <p class="text-sm font-medium text-yellow-600">{{ $pendingMinutes }} minutes</p>
                <p class="text-xs text-gray-500 mt-1">
                    Created {{ $payment->created_at->diffForHumans() }}
                </p>
            </div>
            @endif
            @if($payment->email_data && isset($payment->email_data['manual_verification']))
            <div>
                <label class="text-sm text-gray-600">Manual Verification</label>
                <div class="mt-1">
                    <span class="px-2 py-1 text-xs font-medium bg-indigo-100 text-indigo-800 rounded-full">
                        <i class="fas fa-check-double mr-1"></i> Verified
                    </span>
                    <p class="text-xs text-gray-500 mt-1">
                        By: {{ $payment->email_data['manual_verification']['verified_by_name'] ?? 'Admin' }}
                        @if(isset($payment->email_data['manual_verification']['verified_at']))
                            - {{ \Carbon\Carbon::parse($payment->email_data['manual_verification']['verified_at'])->format('M d, Y H:i') }}
                        @endif
                    </p>
                    @if(isset($payment->email_data['manual_verification']['verification_notes']) && !empty($payment->email_data['manual_verification']['verification_notes']))
                        <p class="text-xs text-gray-600 mt-1 italic">{{ $payment->email_data['manual_verification']['verification_notes'] }}</p>
                    @endif
                </div>
            </div>
            @endif
            @if($payment->expires_at)
            <div>
                <label class="text-sm text-gray-600">Expires At</label>
                <p class="text-sm font-medium {{ $payment->expires_at->isPast() ? 'text-red-600' : ($payment->expires_at->diffInHours(now()) < 2 ? 'text-orange-600' : 'text-gray-900') }}">
                    {{ $payment->expires_at->format('M d, Y H:i:s') }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $payment->expires_at->isPast() ? 'Expired' : 'Expires ' . $payment->expires_at->diffForHumans() }}
                </p>
            </div>
            @endif
        </div>

        <!-- API Status Checks Section -->
        @if($payment->statusChecks->count() > 0)
        <div class="mt-6 pt-6 border-t border-gray-200">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                <h4 class="text-sm sm:text-md font-semibold text-gray-900">
                    <i class="fas fa-sync-alt text-primary mr-2"></i>API Status Checks ({{ $payment->statusChecks->count() }})
                </h4>
                @if($statusChecksCount >= 3)
                    <span class="px-3 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                        Needs Review
                    </span>
                @endif
            </div>
            <div class="space-y-3">
                @foreach($payment->statusChecks->take(5) as $check)
                <div class="bg-gray-50 rounded-lg p-3 sm:p-4 border border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-2 gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                <i class="fas fa-api mr-1"></i> API Check
                            </span>
                            @if($check->business)
                                <span class="text-xs text-gray-600">
                                    Business: {{ $check->business->name }}
                                </span>
                            @endif
                            @if($check->ip_address)
                                <span class="text-xs text-gray-500">
                                    IP: {{ $check->ip_address }}
                                </span>
                            @endif
                        </div>
                        <span class="text-xs text-gray-500">{{ $check->created_at->format('M d, H:i') }}</span>
                    </div>
                    <p class="text-xs text-gray-600">Status at check: <span class="font-medium">{{ ucfirst($check->payment_status) }}</span></p>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Match Attempts Section -->
        @if($payment->matchAttempts->count() > 0)
        <div class="mt-6 pt-6 border-t border-gray-200">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                <h4 class="text-sm sm:text-md font-semibold text-gray-900">
                    <i class="fas fa-search-dollar text-primary mr-2"></i>Match Attempts ({{ $payment->matchAttempts->count() }})
                </h4>
                <a href="{{ route('admin.match-attempts.index', ['transaction_id' => $payment->transaction_id]) }}" 
                   class="text-sm text-primary hover:underline">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="space-y-3">
                @foreach($payment->matchAttempts->take(5) as $attempt)
                <div class="bg-gray-50 rounded-lg p-3 sm:p-4 border border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-2 gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($attempt->match_result === 'matched')
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                    <i class="fas fa-check-circle mr-1"></i> Matched
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                    <i class="fas fa-times-circle mr-1"></i> Unmatched
                                </span>
                            @endif
                            <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded">
                                {{ $attempt->extraction_method ?? 'unknown' }}
                            </span>
                            @if($attempt->name_similarity_percent !== null)
                                <span class="text-xs text-gray-600">
                                    Similarity: {{ $attempt->name_similarity_percent }}%
                                </span>
                            @endif
                            @if($attempt->amount_diff)
                                <span class="text-xs text-gray-600">
                                    Amount diff: ₦{{ number_format(abs($attempt->amount_diff), 2) }}
                                </span>
                            @endif
                        </div>
                        <span class="text-xs text-gray-500">{{ $attempt->created_at->format('M d, H:i') }}</span>
                    </div>
                    <p class="text-xs sm:text-sm text-gray-700 break-words">{{ Str::limit($attempt->reason, 150) }}</p>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($payment->email_data)
        <div class="mt-6 pt-6 border-t border-gray-200">
            <label class="text-sm text-gray-600 mb-2 block">Email Data</label>
            <pre class="bg-gray-50 p-3 sm:p-4 rounded-lg text-xs overflow-x-auto">{{ json_encode($payment->email_data, JSON_PRETTY_PRINT) }}</pre>
        </div>
        @endif
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg p-4 sm:p-6 max-w-md w-full max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Delete Transaction</h3>
        <p class="text-sm text-gray-700 mb-6">
            Are you sure you want to delete transaction <span id="delete-transaction-id" class="font-mono font-semibold"></span>? 
            This action cannot be undone.
        </p>
        <form id="deleteForm" method="POST">
            @csrf
            @method('DELETE')
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeDeleteModal()" 
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" 
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    <i class="fas fa-trash mr-2"></i> Delete Transaction
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Manual Verify Modal -->
<div id="manualVerifyModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg p-4 sm:p-6 max-w-md w-full max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-check-double text-indigo-600 mr-2"></i>Manually Verify Transaction
        </h3>
        <form id="manualVerifyForm" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Transaction ID</label>
                <p class="text-sm font-mono text-gray-900 bg-gray-50 p-2 rounded" id="verify-modal-transaction-id"></p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Expected Amount</label>
                <p class="text-lg font-bold text-gray-900" id="verify-modal-expected-amount"></p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Verified Amount <span class="text-red-500">*</span></label>
                <input type="number" name="verified_amount" step="0.01" min="0" required id="verify-modal-verified-amount"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                <p class="text-xs text-gray-500 mt-1">Enter the amount you verified (defaults to expected amount)</p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Verification Notes</label>
                <textarea name="verification_notes" rows="3" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm" 
                    placeholder="Add notes about the verification (e.g., checked bank statement, confirmed receipt, etc.)..."></textarea>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                <p class="text-xs text-blue-800">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Note:</strong> This will mark the transaction as manually verified. You can approve it later using the "Manual Approve" button.
                </p>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeManualVerifyModal()" 
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" 
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                    <i class="fas fa-check-double mr-2"></i> Verify Transaction
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Manual Approve Modal -->
<div id="manualApproveModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg p-4 sm:p-6 max-w-md w-full max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Manually Approve Transaction</h3>
        <form id="manualApproveForm" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Transaction ID</label>
                <p class="text-sm font-mono text-gray-900 bg-gray-50 p-2 rounded" id="modal-transaction-id"></p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Expected Amount</label>
                <p class="text-lg font-bold text-gray-900" id="modal-expected-amount"></p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Received Amount <span class="text-red-500">*</span></label>
                <input type="number" name="received_amount" step="0.01" min="0" required id="modal-received-amount"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                <p class="text-xs text-gray-500 mt-1">Enter the actual amount received if different from expected</p>
            </div>
            <div class="mb-4">
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="is_mismatch" id="is-mismatch-checkbox" 
                        class="rounded border-gray-300 text-primary focus:ring-primary">
                    <span class="text-sm text-gray-700">Mark as amount mismatch</span>
                </label>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Link Email (Optional)</label>
                <select name="email_id" id="email-select"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm">
                    <option value="">-- No email link --</option>
                    @if(isset($unmatchedEmails) && $unmatchedEmails->count() > 0)
                        @foreach($unmatchedEmails as $email)
                            <option value="{{ $email->id }}">
                                {{ $email->subject }} - {{ $email->from_email }} - ₦{{ number_format($email->amount ?? 0, 2) }} 
                                ({{ $email->email_date ? $email->email_date->format('M d, Y H:i') : 'No date' }})
                            </option>
                        @endforeach
                    @endif
                </select>
                <p class="text-xs text-gray-500 mt-1">Select an email to link to this transaction. This will include email data in the webhook sent to the business.</p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Admin Notes</label>
                <textarea name="admin_notes" rows="3" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm" 
                    placeholder="Add notes about why this is being manually approved..."></textarea>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeManualApproveModal()" 
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" 
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fas fa-check-circle mr-2"></i> Approve & Credit Business
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function checkMatchForPayment(paymentId) {
    const btn = document.getElementById('check-match-btn');
    if (!btn) return;
    
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Checking...';
    
    fetch(`/admin/payments/${paymentId}/check-match`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(`HTTP ${response.status}: ${text.substring(0, 200)}`);
            });
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                throw new Error('Response is not JSON: ' + text.substring(0, 200));
            });
        }
        
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (data.matched) {
                alert(`✅ Payment matched and approved!\n\nEmail: ${data.email.subject || 'N/A'}\nFrom: ${data.email.from_email || 'N/A'}\nAmount: ₦${data.payment.amount.toLocaleString()}`);
                window.location.reload();
            } else {
                let message = '❌ No matching email found.\n\n';
                if (data.matches && data.matches.length > 0) {
                    message += 'Checked Emails:\n';
                    data.matches.forEach(match => {
                        message += `\n• ${match.email_subject || 'No Subject'}: ${match.reason}`;
                        if (match.time_diff_minutes !== null) {
                            message += ` (${match.time_diff_minutes} min difference)`;
                        }
                    });
                } else {
                    message += 'No unmatched emails found to check against.';
                }
                alert(message);
            }
        } else {
            alert('Error: ' + (data.message || 'Failed to check match'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error checking match: ' + error.message + '\n\nCheck browser console (F12) for details.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function markAsExpired(paymentId) {
    if (!confirm('Are you sure you want to mark this transaction as expired? This will stop all matching attempts.')) {
        return;
    }

    fetch(`/admin/payments/${paymentId}/mark-expired`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        if (response.ok) {
            return response.json().catch(() => ({ success: true }));
        }
        return response.json().then(data => Promise.reject(data));
    })
    .then(() => {
        window.location.reload();
    })
    .catch(error => {
        alert('Error: ' + (error.message || 'Failed to mark as expired'));
    });
}

function showDeleteModal(paymentId, transactionId) {
    const form = document.getElementById('deleteForm');
    form.action = `/admin/payments/${paymentId}`;
    document.getElementById('delete-transaction-id').textContent = transactionId;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

function showManualVerifyModal(paymentId, transactionId, expectedAmount) {
    const form = document.getElementById('manualVerifyForm');
    form.action = `/admin/payments/${paymentId}/manual-verify`;
    
    document.getElementById('verify-modal-transaction-id').textContent = transactionId;
    document.getElementById('verify-modal-expected-amount').textContent = '₦' + expectedAmount.toLocaleString('en-NG', {minimumFractionDigits: 2});
    document.getElementById('verify-modal-verified-amount').value = expectedAmount;
    
    document.getElementById('manualVerifyModal').classList.remove('hidden');
}

function closeManualVerifyModal() {
    document.getElementById('manualVerifyModal').classList.add('hidden');
}

function showManualApproveModal(paymentId, transactionId, expectedAmount) {
    const form = document.getElementById('manualApproveForm');
    form.action = `/admin/payments/${paymentId}/manual-approve`;
    
    document.getElementById('modal-transaction-id').textContent = transactionId;
    document.getElementById('modal-expected-amount').textContent = '₦' + expectedAmount.toLocaleString('en-NG', {minimumFractionDigits: 2});
    document.getElementById('modal-received-amount').value = expectedAmount;
    
    document.getElementById('manualApproveModal').classList.remove('hidden');
}

function closeManualApproveModal() {
    document.getElementById('manualApproveModal').classList.add('hidden');
}

function resendWebhook(paymentId) {
    const btn = document.getElementById('resend-webhook-btn');
    if (!btn) return;
    
    if (!confirm('Are you sure you want to resend the webhook notification to the business?')) {
        return;
    }
    
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
    
    fetch(`/admin/payments/${paymentId}/resend-webhook`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => Promise.reject(data));
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('✅ Webhook notification has been queued for resending successfully!');
        } else {
            alert('❌ Error: ' + (data.message || 'Failed to resend webhook'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error: ' + (error.message || 'Failed to resend webhook. Please try again.'));
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

// Auto-check mismatch if amounts differ
document.addEventListener('DOMContentLoaded', function() {
    const receivedAmountInput = document.getElementById('modal-received-amount');
    if (receivedAmountInput) {
        receivedAmountInput.addEventListener('input', function() {
            const expectedText = document.getElementById('modal-expected-amount').textContent;
            const expected = parseFloat(expectedText.replace(/[₦,]/g, '')) || 0;
            const received = parseFloat(this.value) || 0;
            const mismatchCheckbox = document.getElementById('is-mismatch-checkbox');
            
            if (mismatchCheckbox && Math.abs(expected - received) > 0.01) {
                mismatchCheckbox.checked = true;
            } else if (mismatchCheckbox) {
                mismatchCheckbox.checked = false;
            }
        });
    }
    
    // Close modal when clicking outside
    const modal = document.getElementById('manualApproveModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeManualApproveModal();
            }
        });
    }

    // Close delete modal when clicking outside
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    }

    // Close verify modal when clicking outside
    const verifyModal = document.getElementById('manualVerifyModal');
    if (verifyModal) {
        verifyModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeManualVerifyModal();
            }
        });
    }
});
</script>
@endsection

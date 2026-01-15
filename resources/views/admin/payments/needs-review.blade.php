@extends('layouts.admin')

@section('title', 'Transactions Needing Review')
@section('page-title', 'Transactions Needing Review')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 sm:p-4">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle text-yellow-600 text-lg sm:text-xl mr-2 sm:mr-3 mt-1 flex-shrink-0"></i>
            <div class="flex-1 min-w-0">
                <h3 class="text-base sm:text-lg font-semibold text-yellow-900 mb-2">Transactions Requiring Manual Review</h3>
                <p class="text-xs sm:text-sm text-yellow-800">
                    These transactions have been checked 3 or more times by the business via API but are still pending. 
                    Please review them manually and approve if the payment was received.
                </p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 sm:p-4">
        <form method="GET" action="{{ route('admin.payments.needs-review') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 sm:gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Business</label>
                <select name="business_id" class="w-full px-2 sm:px-3 py-2 border border-gray-300 rounded-lg text-xs sm:text-sm focus:ring-primary focus:border-primary">
                    <option value="">All Businesses</option>
                    @foreach(\App\Models\Business::all() as $business)
                        <option value="{{ $business->id }}" {{ request('business_id') == $business->id ? 'selected' : '' }}>
                            {{ $business->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">From Date</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}" 
                    class="w-full px-2 sm:px-3 py-2 border border-gray-300 rounded-lg text-xs sm:text-sm focus:ring-primary focus:border-primary">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}" 
                    class="w-full px-2 sm:px-3 py-2 border border-gray-300 rounded-lg text-xs sm:text-sm focus:ring-primary focus:border-primary">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-3 sm:px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-xs sm:text-sm">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Transactions List -->
    <div class="space-y-3 sm:space-y-4">
        @forelse($payments as $payment)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6 hover:shadow-md transition-shadow overflow-hidden">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 mb-4">
                <div class="flex-1 min-w-0">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 mb-3">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 break-words">{{ $payment->transaction_id }}</h3>
                        <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full self-start">
                            {{ $payment->status_checks_count }} API Checks
                        </span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 text-xs sm:text-sm">
                        <div class="min-w-0">
                            <span class="text-gray-600 block sm:inline">Business:</span>
                            <span class="font-medium text-gray-900 sm:ml-2 break-words">{{ $payment->business->name ?? 'N/A' }}</span>
                        </div>
                        <div class="min-w-0">
                            <span class="text-gray-600 block sm:inline">Amount:</span>
                            <span class="font-bold text-gray-900 sm:ml-2 break-words leading-tight">₦{{ number_format($payment->amount, 2) }}</span>
                        </div>
                        <div class="min-w-0">
                            <span class="text-gray-600 block sm:inline">Payer:</span>
                            <span class="font-medium text-gray-900 sm:ml-2 break-words">{{ $payment->payer_name ?? 'N/A' }}</span>
                        </div>
                        <div class="min-w-0">
                            <span class="text-gray-600 block sm:inline">Created:</span>
                            <span class="font-medium text-gray-900 sm:ml-2 break-words">{{ $payment->created_at->format('M d, Y H:i') }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row lg:flex-col gap-2 lg:ml-4 lg:space-y-0">
                    <a href="{{ route('admin.payments.show', $payment) }}" 
                       class="px-3 sm:px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-xs sm:text-sm text-center whitespace-nowrap">
                        <i class="fas fa-eye mr-2"></i> View Details
                    </a>
                    @if($payment->status === 'approved')
                        <button onclick="resendWebhook({{ $payment->id }})" 
                                id="resend-webhook-btn-{{ $payment->id }}"
                                class="px-3 sm:px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-xs sm:text-sm whitespace-nowrap">
                            <i class="fas fa-paper-plane mr-2"></i> Resend Webhook
                        </button>
                    @else
                        <button onclick="showManualApproveModal({{ $payment->id }}, '{{ $payment->transaction_id }}', {{ $payment->amount }})" 
                                class="px-3 sm:px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-xs sm:text-sm whitespace-nowrap">
                            <i class="fas fa-check-circle mr-2"></i> Manual Approve
                        </button>
                        @if(!$payment->expires_at || $payment->expires_at->isFuture())
                            <button onclick="markAsExpired({{ $payment->id }})" 
                                    class="px-3 sm:px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-xs sm:text-sm whitespace-nowrap">
                                <i class="fas fa-clock mr-2"></i> Mark as Expired
                            </button>
                        @endif
                    @endif
                    <button onclick="showDeleteModal({{ $payment->id }}, '{{ $payment->transaction_id }}')" 
                            class="px-3 sm:px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-xs sm:text-sm whitespace-nowrap">
                        <i class="fas fa-trash mr-2"></i> Delete
                    </button>
                </div>
            </div>

            <!-- Recent API Status Checks -->
            @if($payment->statusChecks->count() > 0)
            <div class="mt-4 pt-4 border-t border-gray-200">
                <h4 class="text-xs sm:text-sm font-semibold text-gray-900 mb-3">Recent API Status Checks</h4>
                <div class="space-y-2">
                    @foreach($payment->statusChecks->take(3) as $check)
                    <div class="bg-gray-50 rounded p-2 sm:p-3 text-xs">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-0 mb-1">
                            <span class="font-medium text-gray-700">{{ $check->created_at->format('M d, H:i') }}</span>
                            <span class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded self-start">API Check</span>
                        </div>
                        <p class="text-gray-600 break-words">
                            <span class="block sm:inline">Business: {{ $check->business->name ?? 'N/A' }}</span>
                            <span class="hidden sm:inline"> | </span>
                            <span class="block sm:inline">Status: <span class="font-medium">{{ ucfirst($check->payment_status) }}</span></span>
                        </p>
                        @if($check->ip_address)
                            <p class="text-gray-500 mt-1 break-words">IP: {{ $check->ip_address }}</p>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @empty
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 sm:p-12 text-center">
            <i class="fas fa-check-circle text-green-300 text-4xl sm:text-5xl mb-4"></i>
            <p class="text-base sm:text-lg font-semibold text-gray-900 mb-2">No transactions need review</p>
            <p class="text-xs sm:text-sm text-gray-600">All transactions are matching successfully or have been reviewed.</p>
        </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($payments->hasPages())
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        {{ $payments->links() }}
    </div>
    @endif
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-3 sm:p-4">
    <div class="bg-white rounded-lg p-4 sm:p-6 max-w-md w-full max-h-[90vh] overflow-y-auto">
        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Delete Transaction</h3>
        <p class="text-xs sm:text-sm text-gray-700 mb-4 sm:mb-6 break-words">
            Are you sure you want to delete transaction <span id="delete-transaction-id" class="font-mono font-semibold break-all"></span>? 
            This action cannot be undone.
        </p>
        <form id="deleteForm" method="POST">
            @csrf
            @method('DELETE')
            <div class="flex flex-col sm:flex-row justify-end gap-2 sm:gap-3 sm:space-x-0">
                <button type="button" onclick="closeDeleteModal()" 
                    class="px-3 sm:px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-xs sm:text-sm">
                    Cancel
                </button>
                <button type="submit" 
                    class="px-3 sm:px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-xs sm:text-sm">
                    <i class="fas fa-trash mr-2"></i> Delete Transaction
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Manual Approve Modal -->
<div id="manualApproveModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-3 sm:p-4">
    <div class="bg-white rounded-lg p-4 sm:p-6 max-w-md w-full max-h-[90vh] overflow-y-auto">
        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Manually Approve Transaction</h3>
        <form id="manualApproveForm" method="POST">
            @csrf
            <div class="mb-3 sm:mb-4">
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Transaction ID</label>
                <p class="text-xs sm:text-sm font-mono text-gray-900 bg-gray-50 p-2 rounded break-all" id="modal-transaction-id"></p>
            </div>
            <div class="mb-3 sm:mb-4">
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Expected Amount</label>
                <p class="text-base sm:text-lg font-bold text-gray-900 break-words" id="modal-expected-amount"></p>
            </div>
            <div class="mb-3 sm:mb-4">
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Received Amount <span class="text-red-500">*</span></label>
                <input type="number" name="received_amount" step="0.01" min="0" required id="modal-received-amount"
                    class="w-full px-2 sm:px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-xs sm:text-sm">
                <p class="text-xs text-gray-500 mt-1">Enter the actual amount received if different from expected</p>
            </div>
            <div class="mb-3 sm:mb-4">
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="is_mismatch" id="is-mismatch-checkbox" 
                        class="rounded border-gray-300 text-primary focus:ring-primary">
                    <span class="text-xs sm:text-sm text-gray-700">Mark as amount mismatch</span>
                </label>
            </div>
            <div class="mb-3 sm:mb-4">
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Link Email (Optional)</label>
                <select name="email_id" id="email-select"
                    class="w-full px-2 sm:px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-xs sm:text-sm">
                    <option value="">-- Loading emails... --</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Select an email to link to this transaction. This will include email data in the webhook sent to the business.</p>
            </div>
            <div class="mb-3 sm:mb-4">
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Admin Notes</label>
                <textarea name="admin_notes" rows="3" 
                    class="w-full px-2 sm:px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-xs sm:text-sm" 
                    placeholder="Add notes about why this is being manually approved..."></textarea>
            </div>
            <div class="flex flex-col sm:flex-row justify-end gap-2 sm:gap-3 sm:space-x-0">
                <button type="button" onclick="closeManualApproveModal()" 
                    class="px-3 sm:px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-xs sm:text-sm">
                    Cancel
                </button>
                <button type="submit" 
                    class="px-3 sm:px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-xs sm:text-sm">
                    <i class="fas fa-check-circle mr-2"></i> Approve & Credit Business
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
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

function showManualApproveModal(paymentId, transactionId, expectedAmount) {
    const form = document.getElementById('manualApproveForm');
    form.action = `/admin/payments/${paymentId}/manual-approve`;
    
    document.getElementById('modal-transaction-id').textContent = transactionId;
    document.getElementById('modal-expected-amount').textContent = '₦' + expectedAmount.toLocaleString('en-NG', {minimumFractionDigits: 2});
    document.getElementById('modal-received-amount').value = expectedAmount;
    
    // Load unmatched emails for this payment
    loadUnmatchedEmails(paymentId, expectedAmount);
    
    document.getElementById('manualApproveModal').classList.remove('hidden');
}

function loadUnmatchedEmails(paymentId, amount) {
    const select = document.getElementById('email-select');
    select.innerHTML = '<option value="">-- Loading emails... --</option>';
    
    fetch(`/admin/payments/${paymentId}/unmatched-emails?amount=${amount}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        select.innerHTML = '<option value="">-- No email link --</option>';
        if (data.emails && data.emails.length > 0) {
            data.emails.forEach(email => {
                const option = document.createElement('option');
                option.value = email.id;
                option.textContent = email.subject.substring(0, 40) + ' - ₦' + parseFloat(email.amount || 0).toLocaleString('en-NG', {minimumFractionDigits: 2});
                select.appendChild(option);
            });
        }
    })
    .catch(error => {
        console.error('Error loading emails:', error);
        select.innerHTML = '<option value="">-- Error loading emails --</option>';
    });
}

function closeManualApproveModal() {
    document.getElementById('manualApproveModal').classList.add('hidden');
}

function resendWebhook(paymentId) {
    const btn = document.getElementById('resend-webhook-btn-' + paymentId);
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
document.getElementById('modal-received-amount')?.addEventListener('input', function() {
    const expected = parseFloat(document.getElementById('modal-expected-amount').textContent.replace(/[₦,]/g, ''));
    const received = parseFloat(this.value) || 0;
    const mismatchCheckbox = document.getElementById('is-mismatch-checkbox');
    
    if (mismatchCheckbox && Math.abs(expected - received) > 0.01) {
        mismatchCheckbox.checked = true;
    } else if (mismatchCheckbox) {
        mismatchCheckbox.checked = false;
    }
});

// Close modal when clicking outside
document.getElementById('manualApproveModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeManualApproveModal();
    }
});

// Close delete modal when clicking outside
document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});
</script>
@endpush
@endsection

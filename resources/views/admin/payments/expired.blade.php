@extends('layouts.admin')

@section('title', 'Expired Transactions')
@section('page-title', 'Expired Transactions')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-red-50 border border-red-200 rounded-lg p-3 sm:p-4">
        <div class="flex items-start">
            <i class="fas fa-clock text-red-600 text-lg sm:text-xl mr-2 sm:mr-3 mt-1 flex-shrink-0"></i>
            <div class="flex-1 min-w-0">
                <h3 class="text-base sm:text-lg font-semibold text-red-900 mb-2">Expired Transactions</h3>
                <p class="text-xs sm:text-sm text-red-800">
                    These transactions have expired (passed their expiration time) but are still pending. 
                    Some may need revisiting if payment was received after expiration.
                </p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 sm:p-4">
        <form method="GET" action="{{ route('admin.payments.expired') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3 sm:gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Transaction ID, Payer, Account..."
                    class="w-full px-2 sm:px-3 py-2 border border-gray-300 rounded-lg text-xs sm:text-sm focus:ring-primary focus:border-primary">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Business</label>
                <select name="business_id" class="w-full px-2 sm:px-3 py-2 border border-gray-300 rounded-lg text-xs sm:text-sm focus:ring-primary focus:border-primary">
                    <option value="">All Businesses</option>
                    @foreach($businesses as $business)
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

    <!-- Transactions Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction ID</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payer</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expired At</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Match Attempts</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($payments as $payment)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-xs sm:text-sm font-mono text-gray-900">{{ $payment->transaction_id }}</div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-xs sm:text-sm text-gray-900">{{ $payment->business->name ?? 'N/A' }}</div>
                                <div class="text-xs text-gray-500">{{ $payment->business->email ?? '' }}</div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-xs sm:text-sm font-semibold text-gray-900">₦{{ number_format($payment->amount, 2) }}</div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-xs sm:text-sm text-gray-900">{{ $payment->payer_name ?? 'N/A' }}</div>
                                @if($payment->payer_account_number)
                                    <div class="text-xs text-gray-500">{{ $payment->payer_account_number }}</div>
                                @endif
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-xs sm:text-sm text-gray-900">{{ $payment->created_at->format('M d, Y') }}</div>
                                <div class="text-xs text-gray-500">{{ $payment->created_at->format('H:i') }}</div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-xs sm:text-sm text-red-600 font-medium">
                                    {{ $payment->expires_at ? $payment->expires_at->format('M d, Y H:i') : 'N/A' }}
                                </div>
                                @if($payment->expires_at)
                                    <div class="text-xs text-gray-500">
                                        {{ $payment->expires_at->diffForHumans() }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                <span class="text-xs sm:text-sm text-gray-900">{{ $payment->match_attempts_count ?? 0 }}</span>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm font-medium">
                                <div class="flex flex-col sm:flex-row gap-1 sm:gap-2">
                                    <a href="{{ route('admin.payments.show', $payment) }}" 
                                        class="text-primary hover:text-primary/80">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </a>
                                    <button onclick="showManualApproveModal({{ $payment->id }}, '{{ $payment->transaction_id }}', {{ $payment->amount }})" 
                                        class="text-green-600 hover:text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i> Approve
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-gray-500 text-sm">
                                <i class="fas fa-inbox text-3xl mb-2 text-gray-300"></i>
                                <p>No expired transactions found.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="flex justify-center">
        {{ $payments->links() }}
    </div>
</div>

<!-- Manual Approve Modal (same as show.blade.php) -->
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
                    <input type="checkbox" name="is_mismatch" id="is-mismatch-checkbox" value="1"
                        class="rounded border-gray-300 text-primary focus:ring-primary">
                    <span class="text-sm text-gray-700">Mark as amount mismatch</span>
                </label>
                <p class="text-xs text-gray-500 mt-1">This will be automatically checked if received amount differs from expected</p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Link Email (Optional)</label>
                <div class="mb-2">
                    <input type="text" id="email-search" placeholder="Search by sender name or email..." 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm"
                        onkeyup="filterEmails(this.value)">
                    <p class="text-xs text-gray-500 mt-1">Type to filter emails by sender name or email address</p>
                </div>
                <select name="email_id" id="email-select" size="8"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm">
                    <option value="">-- No email link --</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Shows all emails created after this transaction. Select an email to link - this will include email data in the webhook sent to the business.</p>
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

@push('scripts')
<script>
function showManualApproveModal(paymentId, transactionId, expectedAmount) {
    const form = document.getElementById('manualApproveForm');
    form.action = `/admin/payments/${paymentId}/manual-approve`;
    
    document.getElementById('modal-transaction-id').textContent = transactionId;
    document.getElementById('modal-expected-amount').textContent = '₦' + expectedAmount.toLocaleString('en-NG', {minimumFractionDigits: 2});
    document.getElementById('modal-received-amount').value = expectedAmount;
    
    // Reset mismatch checkbox
    const mismatchCheckbox = document.getElementById('is-mismatch-checkbox');
    if (mismatchCheckbox) {
        mismatchCheckbox.checked = false;
    }
    
    // Reset email search
    const emailSearch = document.getElementById('email-search');
    if (emailSearch) {
        emailSearch.value = '';
    }
    
    // Load unmatched emails for this payment
    loadUnmatchedEmails(paymentId, expectedAmount);
    
    document.getElementById('manualApproveModal').classList.remove('hidden');
}

function closeManualApproveModal() {
    document.getElementById('manualApproveModal').classList.add('hidden');
}

function loadUnmatchedEmails(paymentId, amount) {
    const emailSelect = document.getElementById('email-select');
    if (!emailSelect) return;
    
    emailSelect.innerHTML = '<option value="">-- Loading emails... --</option>';
    
    fetch(`/admin/payments/${paymentId}/unmatched-emails?amount=${amount}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        emailSelect.innerHTML = '<option value="">-- No email link --</option>';
        if (data.emails && data.emails.length > 0) {
            data.emails.forEach(email => {
                const option = document.createElement('option');
                option.value = email.id;
                option.textContent = `${email.subject} - ${email.from_email} - ₦${parseFloat(email.amount).toLocaleString('en-NG', {minimumFractionDigits: 2})} (${email.email_date || email.created_at})`;
                emailSelect.appendChild(option);
            });
        }
    })
    .catch(error => {
        console.error('Error loading emails:', error);
        emailSelect.innerHTML = '<option value="">-- Error loading emails --</option>';
    });
}

function filterEmails(searchTerm) {
    const emailSelect = document.getElementById('email-select');
    if (!emailSelect) return;
    
    const search = searchTerm.toLowerCase();
    const options = emailSelect.querySelectorAll('option');
    
    options.forEach(option => {
        if (option.value === '') {
            // Always show the "No email link" option
            option.style.display = '';
            return;
        }
        
        const searchText = option.getAttribute('data-search-text') || option.textContent.toLowerCase();
        if (searchText.includes(search)) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
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
});
</script>
@endpush
@endsection

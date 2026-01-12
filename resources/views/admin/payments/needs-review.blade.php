@extends('layouts.admin')

@section('title', 'Transactions Needing Review')
@section('page-title', 'Transactions Needing Review')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle text-yellow-600 text-xl mr-3 mt-1"></i>
            <div class="flex-1">
                <h3 class="text-lg font-semibold text-yellow-900 mb-2">Transactions Requiring Manual Review</h3>
                <p class="text-sm text-yellow-800">
                    These transactions have been checked 3 or more times but still haven't matched automatically. 
                    Please review them manually and approve if the payment was received.
                </p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" action="{{ route('admin.payments.needs-review') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Business</label>
                <select name="business_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary focus:border-primary">
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
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary focus:border-primary">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary focus:border-primary">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Transactions List -->
    <div class="space-y-4">
        @forelse($payments as $payment)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                    <div class="flex items-center space-x-3 mb-2">
                        <h3 class="text-lg font-semibold text-gray-900">{{ $payment->transaction_id }}</h3>
                        <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                            {{ $payment->match_attempts_count }} Failed Attempts
                        </span>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600">Business:</span>
                            <span class="font-medium text-gray-900 ml-2">{{ $payment->business->name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Amount:</span>
                            <span class="font-bold text-gray-900 ml-2">₦{{ number_format($payment->amount, 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Payer:</span>
                            <span class="font-medium text-gray-900 ml-2">{{ $payment->payer_name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Created:</span>
                            <span class="font-medium text-gray-900 ml-2">{{ $payment->created_at->format('M d, Y H:i') }}</span>
                        </div>
                    </div>
                </div>
                <div class="ml-4 flex flex-col space-y-2">
                    <a href="{{ route('admin.payments.show', $payment) }}" 
                       class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-sm text-center">
                        <i class="fas fa-eye mr-2"></i> View Details
                    </a>
                    <button onclick="showManualApproveModal({{ $payment->id }}, '{{ $payment->transaction_id }}', {{ $payment->amount }})" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                        <i class="fas fa-check-circle mr-2"></i> Manual Approve
                    </button>
                </div>
            </div>

            <!-- Recent Match Attempts -->
            @if($payment->matchAttempts->count() > 0)
            <div class="mt-4 pt-4 border-t border-gray-200">
                <h4 class="text-sm font-semibold text-gray-900 mb-3">Recent Match Attempts</h4>
                <div class="space-y-2">
                    @foreach($payment->matchAttempts->take(3) as $attempt)
                    <div class="bg-gray-50 rounded p-3 text-xs">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-medium text-gray-700">{{ $attempt->created_at->format('M d, H:i') }}</span>
                            <span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 rounded">Unmatched</span>
                        </div>
                        <p class="text-gray-600">{{ Str::limit($attempt->reason, 100) }}</p>
                        @if($attempt->amount_diff)
                            <p class="text-gray-500 mt-1">Amount difference: ₦{{ number_format(abs($attempt->amount_diff), 2) }}</p>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @empty
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <i class="fas fa-check-circle text-green-300 text-5xl mb-4"></i>
            <p class="text-lg font-semibold text-gray-900 mb-2">No transactions need review</p>
            <p class="text-sm text-gray-600">All transactions are matching successfully or have been reviewed.</p>
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

<!-- Manual Approve Modal -->
<div id="manualApproveModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg p-6 max-w-md w-full">
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
    
    document.getElementById('manualApproveModal').classList.remove('hidden');
}

function closeManualApproveModal() {
    document.getElementById('manualApproveModal').classList.add('hidden');
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
</script>
@endpush
@endsection

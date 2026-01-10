@extends('layouts.admin')

@section('title', 'Test Transaction - Live')
@section('page-title', 'Test Transaction - Live Monitoring')

@section('content')
<div class="space-y-6">
    <!-- Live Process Status (Top) -->
    <div id="process-status" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">🔄 Live Transaction Process</h3>
        
        <div id="process-steps" class="space-y-4">
            <!-- Steps will be dynamically updated -->
            <div class="text-center text-gray-400 py-8">
                <p>Create a test payment to start monitoring...</p>
            </div>
        </div>
    </div>

    <!-- Transaction Form -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Create Test Payment</h3>
        
        <form id="test-payment-form" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="business_id" class="block text-sm font-medium text-gray-700 mb-1">Business *</label>
                    <select name="business_id" id="business_id" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary">
                        <option value="">Select Business</option>
                        @foreach($businesses as $business)
                            <option value="{{ $business->id }}">{{ $business->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount (₦) *</label>
                    <input type="number" name="amount" id="amount" step="0.01" min="1" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        placeholder="5000.00">
                </div>

                <div>
                    <label for="payer_name" class="block text-sm font-medium text-gray-700 mb-1">Payer Name</label>
                    <input type="text" name="payer_name" id="payer_name"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        placeholder="John Doe">
                </div>

                <div>
                    <label for="bank" class="block text-sm font-medium text-gray-700 mb-1">Bank</label>
                    <input type="text" name="bank" id="bank"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        placeholder="GTBank">
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                <button type="button" id="check-email-btn" class="px-4 py-2 border border-blue-300 rounded-lg text-blue-700 hover:bg-blue-50" style="display: none;">
                    🔍 Check Email Now
                </button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    🚀 Create Test Payment
                </button>
            </div>
        </form>
    </div>

    <!-- Transaction Details -->
    <div id="transaction-details" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" style="display: none;">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Transaction Details</h3>
        <div id="transaction-info" class="space-y-2"></div>
    </div>

    <!-- Activity Logs -->
    <div id="activity-logs" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" style="display: none;">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Activity Logs</h3>
        <div id="logs-container" class="space-y-2 max-h-96 overflow-y-auto"></div>
    </div>

    <!-- Instructions (Bottom) -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-blue-900 mb-4">📋 How to Test</h3>
        <ol class="list-decimal list-inside space-y-2 text-sm text-blue-800">
            <li><strong>Create Test Payment:</strong> Fill in the form above and click "Create Test Payment"</li>
            <li><strong>Note the Account Number:</strong> You'll see an account number assigned to this payment</li>
            <li><strong>Send Real Money:</strong> Transfer the exact amount to the account number shown</li>
            <li><strong>Watch Live Updates:</strong> The process will update automatically showing:
                <ul class="list-disc list-inside ml-6 mt-1 space-y-1">
                    <li>✅ Payment Request Created</li>
                    <li>✅ Account Number Assigned</li>
                    <li>⏳ Checking for Email...</li>
                    <li>✅ Email Received</li>
                    <li>✅ Payment Matched</li>
                    <li>✅ Payment Approved</li>
                    <li>✅ Webhook Sent</li>
                </ul>
            </li>
            <li><strong>Manual Check:</strong> Click "Check Email Now" to manually trigger email checking</li>
        </ol>
        <div class="mt-4 p-3 bg-blue-100 rounded-lg">
            <p class="text-sm text-blue-900">
                <strong>💡 Tip:</strong> The system automatically checks emails every 2 seconds. 
                You can also click "Check Email Now" to check immediately after sending money, 
                You can also manually check emails using the "Check Email Now" button.
            </p>
        </div>
    </div>
</div>

<script>
let currentTransactionId = null;
let statusInterval = null;

// Form submission
document.getElementById('test-payment-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Creating...';
    
    try {
        const response = await fetch('{{ route("admin.test-transaction.create") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentTransactionId = data.payment.transaction_id;
            document.getElementById('check-email-btn').style.display = 'inline-block';
            document.getElementById('transaction-details').style.display = 'block';
            document.getElementById('activity-logs').style.display = 'block';
            
            // Start polling for status
            startStatusPolling();
            
            // Show initial status
            updateProcessStatus('payment_requested', data.payment);
            updateTransactionDetails(data.payment);
            
            alert('✅ Payment created! Transaction ID: ' + data.payment.transaction_id);
        } else {
            alert('❌ Error: ' + data.message);
        }
    } catch (error) {
        alert('❌ Error: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
});

// Manual email check
document.getElementById('check-email-btn').addEventListener('click', async function() {
    if (!currentTransactionId) return;
    
    const btn = this;
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Checking...';
    
    try {
        const response = await fetch('{{ route("admin.test-transaction.check-email") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                transaction_id: currentTransactionId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Refresh status immediately
            fetchStatus();
        } else {
            alert('❌ Error: ' + data.message);
        }
    } catch (error) {
        alert('❌ Error: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
});


// Start polling for status updates
function startStatusPolling() {
    if (statusInterval) {
        clearInterval(statusInterval);
    }
    
    // Fetch immediately
    fetchStatus();
    
    // Then every 2 seconds
    statusInterval = setInterval(fetchStatus, 2000);
}

// Fetch current status
async function fetchStatus() {
    if (!currentTransactionId) return;
    
    try {
        const response = await fetch(`{{ url('admin/test-transaction/status') }}/${currentTransactionId}`);
        const data = await response.json();
        
        if (data.success) {
            updateProcessStatus(data.current_step, data.payment);
            updateTransactionDetails(data.payment);
            updateActivityLogs(data.logs);
            
            // Stop polling if completed
            if (data.current_step === 'completed' || data.current_step === 'rejected') {
                if (statusInterval) {
                    clearInterval(statusInterval);
                    statusInterval = null;
                }
            }
        }
    } catch (error) {
        console.error('Error fetching status:', error);
    }
}

// Update process steps
function updateProcessStatus(step, payment) {
    const steps = [
        { id: 'payment_requested', label: 'Payment Request Created', icon: '📝' },
        { id: 'account_assigned', label: 'Account Number Assigned', icon: '🏦' },
        { id: 'email_received', label: 'Email Received', icon: '📧' },
        { id: 'payment_matched', label: 'Payment Matched', icon: '✅' },
        { id: 'payment_approved', label: 'Payment Approved', icon: '🎉' },
        { id: 'webhook_sent', label: 'Webhook Sent', icon: '🔔' },
        { id: 'completed', label: 'Transaction Completed', icon: '✨' },
    ];
    
    const container = document.getElementById('process-steps');
    let html = '<div class="space-y-3">';
    
    steps.forEach((stepItem, index) => {
        const isActive = step === stepItem.id || 
                        (step === 'completed' && index < steps.length - 1) ||
                        (step === 'webhook_sent' && index <= 5) ||
                        (step === 'payment_approved' && index <= 4) ||
                        (step === 'payment_matched' && index <= 3) ||
                        (step === 'email_received' && index <= 2) ||
                        (step === 'account_assigned' && index <= 1);
        
        const isCurrent = step === stepItem.id;
        const statusClass = isActive ? 'bg-green-50 border-green-300' : 'bg-gray-50 border-gray-200';
        const textClass = isActive ? 'text-green-800' : 'text-gray-500';
        const icon = isActive ? '✅' : (isCurrent ? '⏳' : '⏸️');
        
        html += `
            <div class="border rounded-lg p-4 ${statusClass}">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl">${isActive ? icon : '⏸️'}</span>
                    <div class="flex-1">
                        <div class="font-medium ${textClass}">${stepItem.label}</div>
                        ${isCurrent ? '<div class="text-xs text-blue-600 mt-1">Processing...</div>' : ''}
                    </div>
                    ${isActive ? '<span class="text-green-600">✓</span>' : ''}
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    
    if (payment) {
        html += `
            <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <strong>Transaction ID:</strong> ${payment.transaction_id}
                    </div>
                    <div>
                        <strong>Amount:</strong> ₦${parseFloat(payment.amount).toLocaleString()}
                    </div>
                    <div>
                        <strong>Account Number:</strong> <span class="font-mono font-semibold">${payment.account_number || 'Pending...'}</span>
                    </div>
                    <div>
                        <strong>Account Name:</strong> <span class="font-semibold">${payment.account_name || 'Pending...'}</span>
                    </div>
                    <div>
                        <strong>Bank Name:</strong> <span class="font-semibold">${payment.bank_name || 'Pending...'}</span>
                    </div>
                    <div>
                        <strong>Status:</strong> <span class="font-semibold">${payment.status.toUpperCase()}</span>
                    </div>
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

// Update transaction details
function updateTransactionDetails(payment) {
    const container = document.getElementById('transaction-info');
    container.innerHTML = `
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><strong>Transaction ID:</strong> ${payment.transaction_id}</div>
            <div><strong>Amount:</strong> ₦${parseFloat(payment.amount).toLocaleString()}</div>
            <div><strong>Payer Name:</strong> ${payment.payer_name || 'N/A'}</div>
            <div><strong>Status:</strong> <span class="px-2 py-1 rounded ${payment.status === 'approved' ? 'bg-green-100 text-green-800' : payment.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'}">${payment.status.toUpperCase()}</span></div>
            <div class="col-span-2 border-t pt-2 mt-2">
                <div class="font-semibold text-base mb-2">💰 Payment Details:</div>
                <div class="grid grid-cols-2 gap-4">
                    <div><strong>Account Number:</strong> <span class="font-mono font-semibold text-lg text-primary">${payment.account_number || 'Pending...'}</span></div>
                    <div><strong>Account Name:</strong> <span class="font-semibold text-lg">${payment.account_name || 'Pending...'}</span></div>
                    <div class="col-span-2"><strong>Bank Name:</strong> <span class="font-semibold text-lg">${payment.bank_name || 'Pending...'}</span></div>
                </div>
            </div>
            <div><strong>Created:</strong> ${new Date(payment.created_at).toLocaleString()}</div>
            ${payment.matched_at ? `<div><strong>Matched:</strong> ${new Date(payment.matched_at).toLocaleString()}</div>` : ''}
            ${payment.approved_at ? `<div><strong>Approved:</strong> ${new Date(payment.approved_at).toLocaleString()}</div>` : ''}
        </div>
    `;
}

// Update activity logs
function updateActivityLogs(logs) {
    const container = document.getElementById('logs-container');
    
    if (!logs || logs.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-sm">No activity logs yet...</p>';
        return;
    }
    
    const logLabels = {
        'payment_requested': '📝 Payment Requested',
        'account_assigned': '🏦 Account Assigned',
        'email_received': '📧 Email Received',
        'payment_matched': '✅ Payment Matched',
        'payment_approved': '🎉 Payment Approved',
        'payment_rejected': '❌ Payment Rejected',
        'webhook_sent': '🔔 Webhook Sent',
        'webhook_failed': '⚠️ Webhook Failed',
    };
    
    let html = '';
    logs.forEach(log => {
        const label = logLabels[log.event_type] || log.event_type;
        html += `
            <div class="border-l-4 border-blue-500 pl-4 py-2 bg-gray-50 rounded">
                <div class="flex items-center justify-between">
                    <div class="font-medium text-sm">${label}</div>
                    <div class="text-xs text-gray-500">${new Date(log.created_at).toLocaleString()}</div>
                </div>
                ${log.description ? `<div class="text-sm text-gray-600 mt-1">${log.description}</div>` : ''}
            </div>
        `;
    });
    
    container.innerHTML = html;
    container.scrollTop = container.scrollHeight;
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (statusInterval) {
        clearInterval(statusInterval);
    }
});
</script>
@endsection

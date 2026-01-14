@extends('layouts.admin')

@section('title', 'Payments')
@section('page-title', 'Payments')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Payments</h3>
            <p class="text-sm text-gray-600 mt-1">Manage all payment transactions</p>
        </div>
        <a href="{{ route('admin.payments.needs-review') }}" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center">
            <i class="fas fa-exclamation-triangle mr-2"></i> Needs Review
            @php
                $needsReviewCount = \App\Models\Payment::withCount('statusChecks')
                ->where('status', \App\Models\Payment::STATUS_PENDING)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->having('status_checks_count', '>=', 3)
                ->count();
            @endphp
            @if($needsReviewCount > 0)
                <span class="ml-2 bg-white text-red-600 rounded-full px-2 py-0.5 text-xs font-bold">{{ $needsReviewCount }}</span>
            @endif
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" class="flex items-center space-x-4 flex-wrap">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" id="searchInput" value="{{ request('search') }}" 
                    placeholder="Search by Transaction ID (TXN-...)..." 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent"
                    autocomplete="off">
            </div>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Status</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending (Not Expired)</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
            @if(request('status') === 'pending' || !request('status'))
            <label class="flex items-center space-x-2 text-sm">
                <input type="checkbox" name="unmatched" value="1" {{ request('unmatched') === '1' ? 'checked' : '' }} 
                    class="rounded border-gray-300 text-primary focus:ring-primary">
                <span>Show only unmatched</span>
            </label>
            <label class="flex items-center space-x-2 text-sm">
                <input type="checkbox" name="needs_review" value="1" {{ request('needs_review') === '1' ? 'checked' : '' }} 
                    class="rounded border-gray-300 text-primary focus:ring-primary">
                <span>Needs Review (3+ attempts)</span>
            </label>
            @endif
            <select name="business_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Businesses</option>
                @foreach(\App\Models\Business::all() as $business)
                    <option value="{{ $business->id }}" {{ request('business_id') == $business->id ? 'selected' : '' }}>
                        {{ $business->name }}
                    </option>
                @endforeach
            </select>
            <input type="date" name="from_date" value="{{ request('from_date') }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <input type="date" name="to_date" value="{{ request('to_date') }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark text-sm">
                <i class="fas fa-search mr-2"></i> Search
            </button>
            <a href="{{ route('admin.payments.index') }}" class="text-gray-600 hover:text-gray-900 text-sm px-4 py-2">Clear</a>
        </form>
    </div>

<script>
// Server-side search - form submits to search all records, not just current page
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.querySelector('form[method="GET"]');
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.querySelector('select[name="status"]');
    const businessFilter = document.querySelector('select[name="business_id"]');
    
    // Submit form to server when filters change (server-side search)
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            searchForm.submit();
        });
    }
    
    if (businessFilter) {
        businessFilter.addEventListener('change', function() {
            searchForm.submit();
        });
    }
    
    // Submit form on Enter key in search input
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchForm.submit();
            }
        });
    }
    
    // Submit form when checkboxes change
    const checkboxes = document.querySelectorAll('input[type="checkbox"][name="unmatched"], input[type="checkbox"][name="needs_review"]');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            searchForm.submit();
        });
    });
});
</script>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payer Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expires</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($payments as $payment)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $payment->transaction_id }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $payment->business->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">₦{{ number_format($payment->amount, 2) }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $payment->account_number ?? 'N/A' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $payment->payer_name ?? 'N/A' }}</td>
                        <td class="px-6 py-4">
                            @if($payment->status === 'approved')
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                            @elseif($payment->status === 'pending')
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                    Pending
                                    @if($payment->expires_at)
                                        @if($payment->expires_at->isPast())
                                            <span class="ml-1 text-red-600">(Expired)</span>
                                        @elseif($payment->expires_at->diffInHours(now()) < 2)
                                            <span class="ml-1 text-orange-600">(Expiring Soon)</span>
                                        @endif
                                    @endif
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $payment->created_at->format('M d, Y H:i') }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            @if($payment->expires_at)
                                <div class="{{ $payment->expires_at->isPast() ? 'text-red-600' : ($payment->expires_at->diffInHours(now()) < 2 ? 'text-orange-600' : 'text-gray-600') }}">
                                    {{ $payment->expires_at->format('M d, Y H:i') }}
                                </div>
                                <div class="text-xs text-gray-400 mt-1">
                                    {{ $payment->expires_at->isPast() ? 'Expired' : 'Expires ' . $payment->expires_at->diffForHumans() }}
                                </div>
                            @else
                                <span class="text-gray-400">No expiration</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.payments.show', $payment) }}" class="text-primary hover:underline text-sm" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if($payment->status === 'pending' && (!$payment->expires_at || $payment->expires_at->isFuture()))
                                    <button onclick="checkMatchForPayment({{ $payment->id }})" 
                                        class="text-sm text-green-600 hover:text-green-800 check-match-payment-btn"
                                        data-payment-id="{{ $payment->id }}"
                                        title="Check Match">
                                        <i class="fas fa-search-dollar"></i>
                                    </button>
                                    @if($payment->status_checks_count >= 3)
                                        <span class="text-xs text-red-600 font-medium" title="{{ $payment->status_checks_count }} API checks">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </span>
                                    @endif
                                    <a href="{{ route('admin.match-attempts.index', ['transaction_id' => $payment->transaction_id]) }}" 
                                       class="text-sm text-blue-600 hover:text-blue-800"
                                       title="View Match Attempts">
                                        <i class="fas fa-list"></i>
                                    </a>
                                @endif
                                @if($payment->status === 'pending' && (!$payment->expires_at || $payment->expires_at->isFuture()))
                                    <button onclick="markAsExpired({{ $payment->id }})" 
                                        class="text-sm text-orange-600 hover:text-orange-800"
                                        title="Mark as Expired">
                                        <i class="fas fa-clock"></i>
                                    </button>
                                @endif
                                <button onclick="showDeleteModal({{ $payment->id }}, '{{ $payment->transaction_id }}')" 
                                    class="text-sm text-red-600 hover:text-red-800"
                                    title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">No payments found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $payments->links() }}
        </div>
        @endif
    </div>
</div>

<script>
function checkMatchForPayment(paymentId) {
    const btn = document.querySelector(`.check-match-payment-btn[data-payment-id="${paymentId}"]`);
    if (!btn) return;
    
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Checking...';
    
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
    if (!confirm(`Are you sure you want to delete transaction ${transactionId}? This action cannot be undone.`)) {
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/admin/payments/${paymentId}`;
    
    const csrfToken = document.createElement('input');
    csrfToken.type = 'hidden';
    csrfToken.name = '_token';
    csrfToken.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
    form.appendChild(csrfToken);
    
    const methodField = document.createElement('input');
    methodField.type = 'hidden';
    methodField.name = '_method';
    methodField.value = 'DELETE';
    form.appendChild(methodField);
    
    document.body.appendChild(form);
    form.submit();
}
</script>
@endsection

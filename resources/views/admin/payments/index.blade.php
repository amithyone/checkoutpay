@extends('layouts.admin')

@section('title', 'Payments')
@section('page-title', 'Payments')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Payments</h3>
            <p class="text-sm text-gray-600 mt-1">Manage all payment transactions</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if(request('status') === 'approved' || !request('status'))
            <button onclick="resendFailedWebhooks()" class="bg-orange-600 text-white px-3 py-2 rounded-lg hover:bg-orange-700 flex items-center text-sm">
                <i class="fas fa-redo mr-2"></i> <span class="hidden sm:inline">Resend Failed Webhooks</span><span class="sm:hidden">Resend</span>
            </button>
            @endif
            <a href="{{ route('admin.payments.expired') }}" class="bg-orange-600 text-white px-3 py-2 rounded-lg hover:bg-orange-700 flex items-center text-sm">
                <i class="fas fa-clock mr-2"></i> <span class="hidden sm:inline">Expired</span><span class="sm:hidden">Expired</span>
                @php
                    $expiredCount = \App\Models\Payment::where('status', \App\Models\Payment::STATUS_PENDING)
                    ->where('expires_at', '<=', now())
                    ->count();
                @endphp
                @if($expiredCount > 0)
                    <span class="ml-2 bg-white text-orange-600 rounded-full px-2 py-0.5 text-xs font-bold">{{ $expiredCount }}</span>
                @endif
            </a>
            <a href="{{ route('admin.payments.needs-review') }}" class="bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700 flex items-center text-sm">
                <i class="fas fa-exclamation-triangle mr-2"></i> <span class="hidden sm:inline">Needs Review</span><span class="sm:hidden">Review</span>
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
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
            <div class="sm:col-span-2 lg:col-span-1">
                <label class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" id="searchInput" value="{{ request('search') }}" 
                    placeholder="Transaction ID or Payer Name..." 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent"
                    autocomplete="off">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Status</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Business</label>
                <select name="business_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Businesses</option>
                    @foreach(\App\Models\Business::all() as $business)
                        <option value="{{ $business->id }}" {{ request('business_id') == $business->id ? 'selected' : '' }}>
                            {{ $business->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if(request('status') === 'pending' || !request('status'))
            <div class="sm:col-span-2 lg:col-span-1">
                <label class="block text-xs font-medium text-gray-700 mb-1">Filters</label>
                <div class="space-y-2">
                    <label class="flex items-center space-x-2 text-sm">
                        <input type="checkbox" name="unmatched" value="1" {{ request('unmatched') === '1' ? 'checked' : '' }} 
                            class="rounded border-gray-300 text-primary focus:ring-primary">
                        <span class="text-xs">Unmatched only</span>
                    </label>
                    <label class="flex items-center space-x-2 text-sm">
                        <input type="checkbox" name="needs_review" value="1" {{ request('needs_review') === '1' ? 'checked' : '' }} 
                            class="rounded border-gray-300 text-primary focus:ring-primary">
                        <span class="text-xs">Needs Review</span>
                    </label>
                </div>
            </div>
            @endif
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">From Date</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2 lg:col-span-1 flex items-end gap-2">
                <button type="submit" class="flex-1 bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark text-sm">
                    <i class="fas fa-search mr-2"></i> Search
                </button>
                <a href="{{ route('admin.payments.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm">Clear</a>
            </div>
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
        <!-- Desktop Table View -->
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Website</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payer Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Webhook</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matching Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expires</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($payments as $payment)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $payment->transaction_id }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $payment->business->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            @if($payment->website)
                                <a href="{{ $payment->website->website_url }}" target="_blank" class="text-primary hover:underline" title="{{ $payment->website->website_url }}">
                                    {{ parse_url($payment->website->website_url, PHP_URL_HOST) }}
                                    <i class="fas fa-external-link-alt text-xs ml-1"></i>
                                </a>
                            @else
                                <span class="text-gray-400">N/A</span>
                            @endif
                        </td>
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
                        <td class="px-6 py-4">
                            @if($payment->status === 'approved')
                                @if($payment->webhook_status === 'sent')
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full" title="Sent at: {{ $payment->webhook_sent_at?->format('M d, Y H:i') }}">
                                        <i class="fas fa-check-circle mr-1"></i> Sent
                                    </span>
                                @elseif($payment->webhook_status === 'partial')
                                    <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full" title="Partially sent - some webhooks failed">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> Partial
                                    </span>
                                @elseif($payment->webhook_status === 'failed')
                                    <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full" title="Failed: {{ Str::limit($payment->webhook_last_error ?? 'Unknown error', 50) }}">
                                        <i class="fas fa-times-circle mr-1"></i> Failed
                                    </span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full" title="Not sent yet">
                                        <i class="fas fa-clock mr-1"></i> Pending
                                    </span>
                                @endif
                                @if($payment->webhook_urls_sent)
                                    @php
                                        // Handle both formats: array of strings (URLs) or array of associative arrays
                                        $webhookUrls = $payment->webhook_urls_sent ?? [];
                                        $sentCount = 0;
                                        foreach ($webhookUrls as $w) {
                                            // If it's a string (URL), count as sent (new format stores only sent URLs)
                                            // If it's an array, check the status
                                            if (is_string($w)) {
                                                $sentCount++;
                                            } elseif (is_array($w) && isset($w['status']) && $w['status'] === 'success') {
                                                $sentCount++;
                                            }
                                        }
                                    @endphp
                                    <div class="text-xs text-gray-400 mt-1">
                                        {{ $sentCount }}/{{ count($webhookUrls) }} sent
                                    </div>
                                @endif
                            @else
                                <span class="text-gray-400 text-xs">N/A</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $payment->created_at->format('M d, Y H:i') }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            @if($payment->matched_at && $payment->status === 'approved')
                                @php
                                    $matchingTimeMinutes = $payment->created_at->diffInMinutes($payment->matched_at);
                                @endphp
                                <div class="text-gray-900 font-medium">{{ $matchingTimeMinutes }} min</div>
                                <div class="text-xs text-gray-400 mt-1">
                                    Matched {{ $payment->matched_at->format('M d, Y H:i') }}
                                </div>
                            @elseif($payment->status === 'pending')
                                @php
                                    $pendingMinutes = $payment->created_at->diffInMinutes(now());
                                @endphp
                                <div class="text-yellow-600 font-medium">{{ $pendingMinutes }} min</div>
                                <div class="text-xs text-gray-400 mt-1">Still pending</div>
                            @else
                                <span class="text-gray-400">N/A</span>
                            @endif
                        </td>
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
                                @if($payment->status === 'approved')
                                    @if($payment->webhook_status !== 'sent')
                                        <button onclick="resendWebhook({{ $payment->id }})" 
                                            class="text-sm text-blue-600 hover:text-blue-800"
                                            title="Resend Webhook">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    @endif
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
                        <td colspan="12" class="px-6 py-4 text-center text-sm text-gray-500">No payments found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Mobile Card View -->
        <div class="lg:hidden divide-y divide-gray-200">
            @forelse($payments as $payment)
            <a href="{{ route('admin.payments.show', $payment) }}" class="block p-4 hover:bg-gray-50 transition-colors">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate mb-1">{{ Str::limit($payment->transaction_id, 20) }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $payment->business->name ?? 'N/A' }}
                            @if($payment->website)
                                • {{ parse_url($payment->website->website_url, PHP_URL_HOST) }}
                            @endif
                        </p>
                        <p class="text-xs text-gray-400 mt-1">{{ $payment->created_at->format('M d, Y H:i') }}</p>
                    </div>
                    <div class="ml-3 text-right">
                        @if($payment->status === 'approved')
                            <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                        @elseif($payment->status === 'pending')
                            <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                Pending
                                @if($payment->expires_at && $payment->expires_at->isPast())
                                    <span class="text-red-600">(Expired)</span>
                                @endif
                            </span>
                        @else
                            <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                        @endif
                        @if($payment->status === 'approved' && $payment->webhook_status)
                            <div class="mt-1">
                                @if($payment->webhook_status === 'sent')
                                    <span class="px-2 py-0.5 text-xs bg-green-100 text-green-800 rounded-full">
                                        <i class="fas fa-check-circle text-xs"></i> Webhook Sent
                                    </span>
                                @elseif($payment->webhook_status === 'partial')
                                    <span class="px-2 py-0.5 text-xs bg-yellow-100 text-yellow-800 rounded-full">
                                        <i class="fas fa-exclamation-triangle text-xs"></i> Partial
                                    </span>
                                @elseif($payment->webhook_status === 'failed')
                                    <span class="px-2 py-0.5 text-xs bg-red-100 text-red-800 rounded-full">
                                        <i class="fas fa-times-circle text-xs"></i> Failed
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-800 rounded-full">
                                        <i class="fas fa-clock text-xs"></i> Pending
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <p class="text-xs text-gray-600">Amount</p>
                        <p class="text-base font-bold text-gray-900">₦{{ number_format($payment->amount, 2) }}</p>
                    </div>
                    @if($payment->payer_name)
                    <div>
                        <p class="text-xs text-gray-600">Payer</p>
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $payment->payer_name }}</p>
                    </div>
                    @endif
                    @if($payment->account_number)
                    <div>
                        <p class="text-xs text-gray-600">Account</p>
                        <p class="text-sm font-medium text-gray-900">{{ $payment->account_number }}</p>
                    </div>
                    @endif
                    @if($payment->matched_at && $payment->status === 'approved')
                    <div>
                        <p class="text-xs text-gray-600">Matched</p>
                        <p class="text-sm font-medium text-gray-900">
                            {{ $payment->created_at->diffInMinutes($payment->matched_at) }} min
                        </p>
                    </div>
                    @endif
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                    <div class="flex items-center gap-2">
                        @if($payment->status === 'pending' && (!$payment->expires_at || $payment->expires_at->isFuture()))
                            <button onclick="event.stopPropagation(); checkMatchForPayment({{ $payment->id }})" 
                                class="text-xs text-green-600 hover:text-green-800 px-2 py-1 rounded"
                                title="Check Match">
                                <i class="fas fa-search-dollar"></i>
                            </button>
                        @endif
                        @if($payment->status === 'approved' && $payment->webhook_status !== 'sent')
                            <button onclick="event.stopPropagation(); resendWebhook({{ $payment->id }})" 
                                class="text-xs text-blue-600 hover:text-blue-800 px-2 py-1 rounded"
                                title="Resend Webhook">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        @endif
                    </div>
                    <i class="fas fa-chevron-right text-gray-400"></i>
                </div>
            </a>
            @empty
            <div class="p-8 text-center">
                <i class="fas fa-money-bill-wave text-gray-300 text-4xl mb-4"></i>
                <p class="text-sm text-gray-500">No payments found</p>
            </div>
            @endforelse
        </div>
        
        @if($payments->hasPages())
        <div class="px-4 lg:px-6 py-4 border-t border-gray-200">
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

function resendWebhook(paymentId) {
    if (!confirm('Are you sure you want to resend webhook notification to all configured webhook URLs?')) {
        return;
    }

    fetch(`/admin/payments/${paymentId}/resend-webhook`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            setTimeout(() => window.location.reload(), 1000);
        } else {
            alert('❌ Error: ' + (data.message || 'Failed to resend webhook'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error: ' + error.message);
    });
}

function resendFailedWebhooks() {
    if (!confirm('This will resend webhooks for all approved payments that have failed or pending webhooks. Continue?')) {
        return;
    }

    // Get all payment IDs from current page that are approved and have failed/pending webhooks
    const paymentIds = [];
    document.querySelectorAll('tr').forEach(row => {
        const statusCell = row.querySelector('td:nth-child(7)'); // Status column
        const webhookCell = row.querySelector('td:nth-child(8)'); // Webhook column
        if (statusCell && webhookCell) {
            const statusText = statusCell.textContent.trim();
            const webhookText = webhookCell.textContent.trim();
            if (statusText.includes('Approved') && (webhookText.includes('Failed') || webhookText.includes('Pending') || webhookText.includes('Partial'))) {
                const paymentId = row.querySelector('a[href*="/admin/payments/"]')?.href.match(/\/admin\/payments\/(\d+)/)?.[1];
                if (paymentId) {
                    paymentIds.push(paymentId);
                }
            }
        }
    });

    if (paymentIds.length === 0) {
        alert('No payments with failed or pending webhooks found on this page.');
        return;
    }

    fetch('/admin/payments/resend-webhooks-bulk', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ payment_ids: paymentIds }),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`✅ ${data.message}`);
            setTimeout(() => window.location.reload(), 1000);
        } else {
            alert('❌ Error: ' + (data.message || 'Failed to resend webhooks'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error: ' + error.message);
    });
}
</script>
@endsection

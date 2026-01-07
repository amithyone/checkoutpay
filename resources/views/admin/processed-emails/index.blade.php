@extends('layouts.admin')

@section('title', 'Email Inbox')
@section('page-title', 'Email Inbox')

@section('content')
<div class="space-y-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Emails</p>
                    <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['total']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-envelope text-indigo-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Matched</p>
                    <h3 class="text-2xl font-bold text-green-600">{{ number_format($stats['matched']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Unmatched</p>
                    <h3 class="text-2xl font-bold text-yellow-600">{{ number_format($stats['unmatched']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
        <form method="GET" action="{{ route('admin.processed-emails.index') }}" id="searchForm" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" id="searchInput" value="{{ request('search') }}" 
                    placeholder="Search by Subject, From, Amount, or Sender (type to filter)..." 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    autocomplete="off">
            </div>
            
            <div class="min-w-[150px]">
                <select name="status" id="statusFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="">All Status</option>
                    <option value="matched" {{ request('status') === 'matched' ? 'selected' : '' }}>Matched</option>
                    <option value="unmatched" {{ request('status') === 'unmatched' ? 'selected' : '' }}>Unmatched</option>
                </select>
            </div>

            <div class="min-w-[200px]">
                <select name="email_account_id" id="emailAccountFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="">All Email Accounts</option>
                    @foreach($emailAccounts as $account)
                        <option value="{{ $account->id }}" {{ request('email_account_id') == $account->id ? 'selected' : '' }}>
                            {{ $account->email }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="button" onclick="clearFilters()" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                <i class="fas fa-times mr-2"></i> Clear
            </button>
        </form>
    </div>

    <!-- Emails Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">From</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sender</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody id="emailsTableBody" class="divide-y divide-gray-200">
                    @forelse($emails as $email)
                    <tr class="email-row hover:bg-gray-50 {{ !$email->is_matched ? 'bg-yellow-50/30' : '' }}" 
                        data-subject="{{ strtolower($email->subject ?? 'no subject') }}"
                        data-from-email="{{ strtolower($email->from_email ?? '') }}"
                        data-from-name="{{ strtolower($email->from_name ?? '') }}"
                        data-sender-name="{{ strtolower($email->sender_name ?? '') }}"
                        data-amount="{{ $email->amount ?? '' }}"
                        data-status="{{ $email->is_matched ? 'matched' : 'unmatched' }}"
                        data-email-account-id="{{ $email->email_account_id ?? '' }}">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900" title="{{ $email->subject ?? 'No Subject' }}">
                                @php
                                    $subject = $email->subject ?? 'No Subject';
                                    echo strlen($subject) > 50 ? substr($subject, 0, 50) . '...' : $subject;
                                @endphp
                            </div>
                            @if($email->emailAccount)
                                <div class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-envelope mr-1"></i>{{ $email->emailAccount->email }}
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $email->from_email }}
                            @if($email->from_name)
                                <div class="text-xs text-gray-500">{{ $email->from_name }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            @if($email->amount)
                                <span class="font-medium">₦{{ number_format($email->amount, 2) }}</span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $email->sender_name ?? '-' }}
                        </td>
                        <td class="px-6 py-4">
                            @if($email->is_matched)
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                    <i class="fas fa-check-circle mr-1"></i> Matched
                                </span>
                                @if($email->matchedPayment)
                                    <div class="text-xs text-gray-500 mt-1">
                                        <a href="{{ route('admin.payments.show', $email->matchedPayment) }}" class="text-primary hover:underline">
                                            {{ $email->matchedPayment->transaction_id }}
                                        </a>
                                    </div>
                                @endif
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                    <i class="fas fa-clock mr-1"></i> Unmatched
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $email->email_date ? $email->email_date->format('M d, Y H:i') : $email->created_at->format('M d, Y H:i') }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.processed-emails.show', $email) }}" 
                                    class="text-sm text-primary hover:underline">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                                @if(!$email->is_matched && $email->amount)
                                    <button onclick="checkMatch({{ $email->id }})" 
                                        class="text-sm text-green-600 hover:underline check-match-btn"
                                        data-email-id="{{ $email->id }}">
                                        <i class="fas fa-search mr-1"></i> Check Match
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                            @if(request()->hasAny(['search', 'status', 'email_account_id']))
                                No emails found matching your search criteria.
                                <a href="{{ route('admin.processed-emails.index') }}" class="text-primary hover:underline ml-2">Clear filters</a>
                            @else
                                No emails found
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($emails->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $emails->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</div>

<script>
let searchTimeout;

function filterEmails() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const emailAccountFilter = document.getElementById('emailAccountFilter');
    const emailRows = document.querySelectorAll('.email-row');
    
    if (!searchInput || !emailRows.length) {
        return;
    }
    
    const searchTerm = (searchInput.value || '').toLowerCase().trim();
    const statusValue = statusFilter ? statusFilter.value : '';
    const emailAccountValue = emailAccountFilter ? emailAccountFilter.value : '';
    
    let visibleCount = 0;
    
    emailRows.forEach(row => {
        const subject = (row.dataset.subject || '').toLowerCase();
        const fromEmail = (row.dataset.fromEmail || '').toLowerCase();
        const fromName = (row.dataset.fromName || '').toLowerCase();
        const senderName = (row.dataset.senderName || '').toLowerCase();
        const amount = row.dataset.amount || '';
        const status = row.dataset.status || '';
        const emailAccountId = row.dataset.emailAccountId || '';
        
        // Search filter
        let matchesSearch = true;
        if (searchTerm) {
            const numericSearch = searchTerm.replace(/[^0-9.]/g, '');
            matchesSearch = 
                subject.includes(searchTerm) ||
                fromEmail.includes(searchTerm) ||
                fromName.includes(searchTerm) ||
                senderName.includes(searchTerm) ||
                (amount && numericSearch && amount.toString().includes(numericSearch));
        }
        
        // Status filter
        let matchesStatus = !statusValue || status === statusValue;
        
        // Email account filter
        let matchesEmailAccount = !emailAccountValue || emailAccountId === emailAccountValue;
        
        // Show/hide row
        if (matchesSearch && matchesStatus && matchesEmailAccount) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update empty state
    const tbody = document.getElementById('emailsTableBody');
    if (!tbody) return;
    
    let noResultsRow = tbody.querySelector('.no-results-row');
    
    if (visibleCount === 0 && emailRows.length > 0) {
        // Show no results message
        if (!noResultsRow) {
            noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row';
            noResultsRow.innerHTML = `
                <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                    No emails found matching your search criteria.
                    <button onclick="clearFilters()" class="text-primary hover:underline ml-2">Clear filters</button>
                </td>
            `;
            tbody.appendChild(noResultsRow);
        }
        noResultsRow.style.display = '';
    } else {
        // Hide no results row if we have results
        if (noResultsRow) {
            noResultsRow.style.display = 'none';
        }
    }
}

// Real-time search as you type
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const emailAccountFilter = document.getElementById('emailAccountFilter');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterEmails, 200); // Debounce 200ms
        });
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', filterEmails);
    }
    
    if (emailAccountFilter) {
        emailAccountFilter.addEventListener('change', filterEmails);
    }
    
    // Initial filter on page load (if there are query params)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('search') || urlParams.has('status') || urlParams.has('email_account_id')) {
        filterEmails();
    }
});

// Clear filters function
function clearFilters() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const emailAccountFilter = document.getElementById('emailAccountFilter');
    
    if (searchInput) searchInput.value = '';
    if (statusFilter) statusFilter.value = '';
    if (emailAccountFilter) emailAccountFilter.value = '';
    
    filterEmails();
    
    // Reload page to reset pagination
    window.location.href = '{{ route("admin.processed-emails.index") }}';
}

// Check match function
function checkMatch(emailId) {
    const btn = document.querySelector(`.check-match-btn[data-email-id="${emailId}"]`);
    if (!btn) return;
    
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Checking...';
    
    fetch(`/admin/processed-emails/${emailId}/check-match`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        // Check if response is OK
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(`HTTP ${response.status}: ${text.substring(0, 200)}`);
            });
        }
        
        // Check content type
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
                alert(`✅ Payment matched and approved!\nTransaction ID: ${data.payment.transaction_id}\nAmount: ₦${data.payment.amount.toLocaleString()}`);
                window.location.reload();
            } else {
                let message = '❌ No matching payment found.\n\n';
                if (data.matches && data.matches.length > 0) {
                    message += 'Match Results:\n';
                    data.matches.forEach(match => {
                        message += `\n• ${match.transaction_id}: ${match.reason}`;
                        if (match.time_diff_minutes !== null) {
                            message += ` (${match.time_diff_minutes} min difference)`;
                        }
                    });
                } else {
                    message += 'No pending payments found to match against.';
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
</script>
@endsection

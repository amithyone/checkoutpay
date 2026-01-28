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
    <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6 border border-gray-200">
        <form method="GET" action="{{ route('admin.processed-emails.index') }}" id="searchForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="sm:col-span-2 lg:col-span-1">
                <label class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" id="searchInput" value="{{ request('search') }}" 
                    placeholder="Subject, From, Amount..." 
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    autocomplete="off">
            </div>
            
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="status" id="statusFilter" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="">All Status</option>
                    <option value="matched" {{ request('status') === 'matched' ? 'selected' : '' }}>Matched</option>
                    <option value="unmatched" {{ request('status') === 'unmatched' ? 'selected' : '' }}>Unmatched</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Email Account</label>
                <select name="email_account_id" id="emailAccountFilter" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="">All Accounts</option>
                    @foreach($emailAccounts as $account)
                        <option value="{{ $account->id }}" {{ request('email_account_id') == $account->id ? 'selected' : '' }}>
                            {{ Str::limit($account->email, 30) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2 lg:col-span-1 flex items-end gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark text-sm">
                    <i class="fas fa-search mr-2"></i> Search
                </button>
                <a href="{{ route('admin.processed-emails.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm">
                    <i class="fas fa-times mr-2"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Emails Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <!-- Desktop Table View -->
        <div class="hidden lg:block overflow-x-auto">
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
                        data-email-account-id="{{ $email->email_account_id ?? '' }}"
                        data-transaction-id="{{ strtolower($email->matchedPayment ? $email->matchedPayment->transaction_id : '') }}">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900" title="{{ $email->subject ?? 'No Subject' }}">
                                @php
                                    $subject = $email->subject ?? 'No Subject';
                                    echo strlen($subject) > 50 ? substr($subject, 0, 50) . '...' : $subject;
                                @endphp
                            </div>
                            <div class="flex items-center gap-2 mt-1">
                                @if($email->source === 'gmail_api')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                        <i class="fas fa-google mr-1"></i> Gmail API
                                    </span>
                                @elseif($email->source === 'imap')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        <i class="fas fa-server mr-1"></i> IMAP
                                    </span>
                                @elseif($email->source === 'direct_filesystem')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                        <i class="fas fa-file-alt mr-1"></i> Direct
                                    </span>
                                @endif
                                @if($email->emailAccount)
                                    <span class="text-xs text-gray-500">
                                        <i class="fas fa-envelope mr-1"></i>{{ Str::limit($email->emailAccount->email, 20) }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 break-all">
                            {{ Str::limit($email->from_email, 30) }}
                            @if($email->from_name)
                                <div class="text-xs text-gray-500">{{ Str::limit($email->from_name, 25) }}</div>
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
                            {{ Str::limit($email->sender_name ?? '-', 20) }}
                        </td>
                        <td class="px-6 py-4">
                            @if($email->is_matched)
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                    <i class="fas fa-check-circle mr-1"></i> Matched
                                </span>
                                @if($email->matchedPayment)
                                    <div class="text-xs text-gray-500 mt-1">
                                        <a href="{{ route('admin.payments.show', $email->matchedPayment) }}" class="text-primary hover:underline">
                                            {{ Str::limit($email->matchedPayment->transaction_id, 15) }}
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
                                @if(!$email->is_matched)
                                    <button onclick="checkMatch({{ $email->id }})" 
                                        class="text-sm text-green-600 hover:underline check-match-btn"
                                        data-email-id="{{ $email->id }}">
                                        <i class="fas fa-search mr-1"></i> Match
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
        
        <!-- Mobile Card View -->
        <div class="lg:hidden divide-y divide-gray-200">
            @forelse($emails as $email)
            <a href="{{ route('admin.processed-emails.show', $email) }}" class="block p-4 hover:bg-gray-50 transition-colors {{ !$email->is_matched ? 'bg-yellow-50/30' : '' }}">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate mb-1">{{ Str::limit($email->subject ?? 'No Subject', 40) }}</p>
                        <p class="text-xs text-gray-500 break-all mb-1">{{ Str::limit($email->from_email, 35) }}</p>
                        <div class="flex flex-wrap items-center gap-2 mt-1">
                            @if($email->source === 'gmail_api')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                    <i class="fas fa-google text-xs"></i>
                                </span>
                            @elseif($email->source === 'imap')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                    <i class="fas fa-server text-xs"></i>
                                </span>
                            @elseif($email->source === 'direct_filesystem')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                    <i class="fas fa-file-alt text-xs"></i>
                                </span>
                            @endif
                            <span class="text-xs text-gray-400">
                                {{ $email->email_date ? $email->email_date->format('M d, H:i') : $email->created_at->format('M d, H:i') }}
                            </span>
                        </div>
                    </div>
                    <div class="ml-3 text-right">
                        @if($email->is_matched)
                            <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                <i class="fas fa-check-circle text-xs"></i> Matched
                            </span>
                        @else
                            <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                <i class="fas fa-clock text-xs"></i> Unmatched
                            </span>
                        @endif
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    @if($email->amount)
                    <div>
                        <p class="text-xs text-gray-600">Amount</p>
                        <p class="text-base font-bold text-gray-900">₦{{ number_format($email->amount, 2) }}</p>
                    </div>
                    @endif
                    @if($email->sender_name)
                    <div>
                        <p class="text-xs text-gray-600">Sender</p>
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $email->sender_name }}</p>
                    </div>
                    @endif
                    @if($email->matchedPayment)
                    <div class="col-span-2">
                        <p class="text-xs text-gray-600">Transaction</p>
                        <p class="text-sm font-medium text-primary truncate">{{ $email->matchedPayment->transaction_id }}</p>
                    </div>
                    @endif
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                    @if(!$email->is_matched)
                        <button onclick="event.stopPropagation(); checkMatch({{ $email->id }})" 
                            class="text-xs text-green-600 hover:text-green-800 px-2 py-1 rounded"
                            title="Check Match">
                            <i class="fas fa-search-dollar"></i> Check Match
                        </button>
                    @endif
                    <i class="fas fa-chevron-right text-gray-400"></i>
                </div>
            </a>
            @empty
            <div class="p-8 text-center">
                <i class="fas fa-inbox text-gray-300 text-4xl mb-4"></i>
                <p class="text-sm text-gray-500">
                    @if(request()->hasAny(['search', 'status', 'email_account_id']))
                        No emails found matching your search criteria.
                        <a href="{{ route('admin.processed-emails.index') }}" class="text-primary hover:underline block mt-2">Clear filters</a>
                    @else
                        No emails found
                    @endif
                </p>
            </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($emails->hasPages())
            <div class="px-4 lg:px-6 py-4 border-t border-gray-200">
                {{ $emails->links() }}
            </div>
        @endif
    </div>
</div>

<script>
// Server-side search - form submits to search all records, not just current page
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('searchForm');
    if (!searchForm) {
        console.error('Search form not found');
        return;
    }
    
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const emailAccountFilter = document.getElementById('emailAccountFilter');
    
    // Submit form to server when filters change (server-side search)
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            searchForm.submit();
        });
    }
    
    if (emailAccountFilter) {
        emailAccountFilter.addEventListener('change', function() {
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
    
});

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

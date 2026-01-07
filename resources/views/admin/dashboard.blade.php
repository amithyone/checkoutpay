@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Payments -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Payments</p>
                    <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['payments']['total']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-blue-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center text-sm">
                <span class="text-gray-600">Pending: {{ $stats['payments']['pending'] }}</span>
                <span class="mx-2">•</span>
                <span class="text-green-600">Approved: {{ $stats['payments']['approved'] }}</span>
            </div>
        </div>

        <!-- Total Businesses -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Businesses</p>
                    <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['businesses']['total']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-building text-green-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-sm text-gray-600">Active: {{ $stats['businesses']['active'] }}</span>
            </div>
        </div>

        <!-- Pending Withdrawals -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Pending Withdrawals</p>
                    <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['withdrawals']['pending']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-hand-holding-usd text-yellow-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-sm text-gray-600">Total: {{ $stats['withdrawals']['total'] }}</span>
            </div>
        </div>

        <!-- Account Numbers -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Account Numbers</p>
                    <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['account_numbers']['total']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-credit-card text-purple-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center text-sm">
                <span class="text-gray-600">Pool: {{ $stats['account_numbers']['pool'] }}</span>
                <span class="mx-2">•</span>
                <span class="text-gray-600">Business: {{ $stats['account_numbers']['business_specific'] }}</span>
            </div>
        </div>

        <!-- Stored Emails -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Stored Emails</p>
                    <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['stored_emails']['total']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-envelope text-indigo-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center text-sm">
                <span class="text-green-600">Matched: {{ $stats['stored_emails']['matched'] }}</span>
                <span class="mx-2">•</span>
                <span class="text-yellow-600">Unmatched: {{ $stats['stored_emails']['unmatched'] }}</span>
            </div>
        </div>
    </div>

    <!-- Email Monitoring Actions -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Email Monitoring</h3>
                <p class="text-sm text-gray-600 mt-1">Manually trigger email fetching or check for transaction updates</p>
            </div>
            <div class="flex items-center gap-4">
                <button onclick="fetchEmails()" id="fetch-emails-btn" 
                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                    <i class="fas fa-envelope mr-2"></i> Fetch Emails
                </button>
                <button onclick="checkTransactionUpdates()" id="check-updates-btn"
                    class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 flex items-center">
                    <i class="fas fa-sync-alt mr-2"></i> Check Transaction Updates
                </button>
            </div>
        </div>
        <div id="monitoring-result" class="mt-4 hidden"></div>
    </div>

    <!-- Cron Job Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-blue-900 mb-2">
            <i class="fas fa-clock mr-2"></i> Cron Job URL
        </h3>
        <p class="text-sm text-blue-800 mb-4">
            Use this URL in your cron job service (e.g., cron-job.org, EasyCron) to automatically check emails:
        </p>
        <div class="bg-white rounded-lg p-4 border border-blue-200">
            <code class="text-sm text-gray-900 break-all">{{ url('/cron/monitor-emails') }}</code>
            <button onclick="copyCronUrl()" class="ml-4 text-blue-600 hover:text-blue-800 text-sm">
                <i class="fas fa-copy mr-1"></i> Copy URL
            </button>
        </div>
        <p class="text-xs text-blue-700 mt-2">
            <strong>Recommended frequency:</strong> Every 5-10 minutes
        </p>
    </div>

    <!-- Stored Emails Section -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Recent Stored Emails</h3>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-600">
                    Total: <span class="font-medium">{{ number_format($stats['stored_emails']['total']) }}</span>
                </span>
                <span class="text-sm text-green-600">
                    Matched: <span class="font-medium">{{ number_format($stats['stored_emails']['matched']) }}</span>
                </span>
                <span class="text-sm text-yellow-600">
                    Unmatched: <span class="font-medium">{{ number_format($stats['stored_emails']['unmatched']) }}</span>
                </span>
            </div>
        </div>
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
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($recentStoredEmails as $email)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">
                                {{ Str::limit($email->subject ?? 'No Subject', 50) }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $email->from_email }}
                            @if($email->emailAccount)
                                <div class="text-xs text-gray-500">{{ $email->emailAccount->email }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            @if($email->amount)
                                ₦{{ number_format($email->amount, 2) }}
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
                                    Matched
                                </span>
                                @if($email->matchedPayment)
                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ $email->matchedPayment->transaction_id }}
                                    </div>
                                @endif
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                    Unmatched
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $email->email_date ? $email->email_date->format('M d, Y H:i') : $email->created_at->format('M d, Y H:i') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No stored emails found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Payments -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Recent Payments</h3>
                <a href="{{ route('admin.payments.index') }}" class="text-sm text-primary hover:underline">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($recentPayments as $payment)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <a href="{{ route('admin.payments.show', $payment) }}" class="text-sm font-medium text-primary hover:underline">
                                    {{ $payment->transaction_id }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">₦{{ number_format($payment->amount, 2) }}</td>
                            <td class="px-6 py-4">
                                @if($payment->status === 'approved')
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                                @elseif($payment->status === 'pending')
                                    <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $payment->created_at->format('M d, Y') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No payments found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pending Withdrawals -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Pending Withdrawals</h3>
                <a href="{{ route('admin.withdrawals.index') }}" class="text-sm text-primary hover:underline">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($pendingWithdrawals as $withdrawal)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $withdrawal->business->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">₦{{ number_format($withdrawal->amount, 2) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $withdrawal->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4">
                                <a href="{{ route('admin.withdrawals.show', $withdrawal) }}" class="text-sm text-primary hover:underline">Review</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No pending withdrawals</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function fetchEmails() {
    const btn = document.getElementById('fetch-emails-btn');
    const resultDiv = document.getElementById('monitoring-result');
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Fetching...';
    resultDiv.classList.add('hidden');
    
    fetch('{{ route("admin.email-monitor.fetch") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.className = 'mt-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg';
            resultDiv.innerHTML = '<strong>Success!</strong> ' + data.message + '<pre class="mt-2 text-xs overflow-auto max-h-40">' + (data.output || '') + '</pre>';
        } else {
            resultDiv.className = 'mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg';
            resultDiv.innerHTML = '<strong>Error!</strong> ' + data.message;
        }
        resultDiv.classList.remove('hidden');
    })
    .catch(error => {
        resultDiv.className = 'mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg';
        resultDiv.innerHTML = '<strong>Error!</strong> ' + error.message;
        resultDiv.classList.remove('hidden');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function checkTransactionUpdates() {
    const btn = document.getElementById('check-updates-btn');
    const resultDiv = document.getElementById('monitoring-result');
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Checking...';
    resultDiv.classList.add('hidden');
    
    fetch('{{ route("admin.email-monitor.check-updates") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.className = 'mt-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg';
            let output = data.re_extract_output || '';
            output += '\n' + (data.monitor_output || '');
            resultDiv.innerHTML = '<strong>Success!</strong> ' + data.message + '<pre class="mt-2 text-xs overflow-auto max-h-40">' + output + '</pre>';
            // Reload page after 2 seconds to show updated data
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            resultDiv.className = 'mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg';
            resultDiv.innerHTML = '<strong>Error!</strong> ' + data.message;
        }
        resultDiv.classList.remove('hidden');
    })
    .catch(error => {
        resultDiv.className = 'mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg';
        resultDiv.innerHTML = '<strong>Error!</strong> ' + error.message;
        resultDiv.classList.remove('hidden');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function copyCronUrl() {
    const url = '{{ url("/cron/monitor-emails") }}';
    navigator.clipboard.writeText(url).then(() => {
        alert('Cron URL copied to clipboard!');
    }).catch(() => {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = url;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Cron URL copied to clipboard!');
    });
}
</script>
@endsection

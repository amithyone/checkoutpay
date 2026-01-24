@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <!-- Daily Stats Cards -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg shadow-sm border-2 border-blue-200 p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-chart-line text-blue-600 mr-2"></i>
            Today's Statistics
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- Daily Amount Received -->
            <div class="bg-white rounded-lg shadow-sm p-4 border border-blue-100">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-gray-600">Amount Received Today</p>
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-money-bill-wave text-green-600 text-sm"></i>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900">₦{{ number_format($stats['daily']['amount_received'], 2) }}</h3>
                @if($stats['daily']['amount_change_percent'] != 0)
                    <div class="mt-2 flex items-center text-xs">
                        @if($stats['daily']['amount_change_percent'] > 0)
                            <span class="text-green-600">
                                <i class="fas fa-arrow-up mr-1"></i>
                                {{ abs($stats['daily']['amount_change_percent']) }}% vs yesterday
                            </span>
                        @else
                            <span class="text-red-600">
                                <i class="fas fa-arrow-down mr-1"></i>
                                {{ abs($stats['daily']['amount_change_percent']) }}% vs yesterday
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Daily Transactions -->
            <div class="bg-white rounded-lg shadow-sm p-4 border border-blue-100">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-gray-600">Transactions Today</p>
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exchange-alt text-blue-600 text-sm"></i>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['daily']['transactions_count']) }}</h3>
                <div class="mt-2 text-xs text-gray-500">
                    {{ $stats['daily']['approved_count'] }} approved
                </div>
            </div>

            <!-- Daily Approved -->
            <div class="bg-white rounded-lg shadow-sm p-4 border border-green-100">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-gray-600">Approved Today</p>
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-sm"></i>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['daily']['approved_count']) }}</h3>
                <div class="mt-2 text-xs text-gray-500">
                    {{ $stats['daily']['transactions_count'] > 0 ? round(($stats['daily']['approved_count'] / $stats['daily']['transactions_count']) * 100, 1) : 0 }}% of total
                </div>
            </div>

            <!-- Daily Pending -->
            <div class="bg-white rounded-lg shadow-sm p-4 border border-yellow-100">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-gray-600">Pending Today</p>
                    <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600 text-sm"></i>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['daily']['pending_count']) }}</h3>
                <div class="mt-2 text-xs text-gray-500">
                    Awaiting approval
                </div>
            </div>

            <!-- View Stats Link -->
            <div class="bg-white rounded-lg shadow-sm p-4 border border-purple-100 flex items-center justify-center">
                <a href="{{ route('admin.stats.index') }}" class="flex flex-col items-center text-center hover:opacity-80 transition-opacity">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-2">
                        <i class="fas fa-chart-bar text-purple-600 text-xl"></i>
                    </div>
                    <p class="text-sm font-semibold text-gray-900">View All Stats</p>
                    <p class="text-xs text-gray-500 mt-1">Daily, Monthly, Yearly</p>
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-6">
        <!-- Global Match Trigger Button -->
        <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-lg shadow-sm p-6 border-2 border-green-200 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">
                        <i class="fas fa-search-dollar text-green-600 mr-2"></i>Global Match
                    </h3>
                    <p class="text-xs text-gray-600">Trigger matching for all unmatched items</p>
                </div>
            </div>
            <button onclick="triggerGlobalMatch()" 
                    id="global-match-btn"
                    class="w-full bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-green-700 flex items-center justify-center font-medium transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>
                <span>Run Global Match</span>
            </button>
            <p class="text-xs text-gray-500 mt-3 text-center">
                Uses new matching logic with full logging
            </p>
        </div>

        <!-- Extract Missing Names Button -->
        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg shadow-sm p-6 border-2 border-blue-200 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">
                        <i class="fas fa-user-edit text-blue-600 mr-2"></i>Extract Names
                    </h3>
                    <p class="text-xs text-gray-600">Extract missing names from emails</p>
                </div>
            </div>
            <button onclick="extractMissingNames()" 
                    id="extract-names-btn"
                    class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 flex items-center justify-center font-medium transition-colors">
                <i class="fas fa-magic mr-2"></i>
                <span>Extract Missing Names</span>
            </button>
            <p class="text-xs text-gray-500 mt-3 text-center">
                Extracts from description field pattern
            </p>
        </div>

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
            <div class="mt-3 pt-3 border-t border-gray-200">
                <div class="text-xs text-gray-500">
                    <div class="mb-1">
                        <i class="fas fa-check-circle text-green-500 mr-1"></i>
                        Payments: <span class="font-semibold text-gray-900">{{ number_format($stats['account_numbers']['total_payments_received_count']) }}</span>
                    </div>
                    <div>
                        <i class="fas fa-money-bill-wave text-green-500 mr-1"></i>
                        Amount: <span class="font-semibold text-gray-900">₦{{ number_format($stats['account_numbers']['total_payments_received_amount'], 2) }}</span>
                    </div>
                </div>
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
            <div class="mt-3 pt-3 border-t border-gray-200 flex items-center text-xs text-gray-500">
                <span><i class="fas fa-server text-blue-500 mr-1"></i> IMAP: {{ $stats['stored_emails']['imap'] }}</span>
                <span class="mx-2">•</span>
                <span><i class="fas fa-google text-red-500 mr-1"></i> Gmail API: {{ $stats['stored_emails']['gmail_api'] }}</span>
                <span class="mx-2">•</span>
                <span><i class="fas fa-file-alt text-gray-500 mr-1"></i> Direct: {{ $stats['stored_emails']['direct_filesystem'] }}</span>
            </div>
        </div>

        <!-- Total Match Similarity Score -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Match Similarity Score</p>
                    <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['match_similarity']['total_score']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-percentage text-purple-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center text-sm">
                <span class="text-gray-600">Total Attempts: {{ number_format($stats['match_similarity']['total_attempts']) }}</span>
                <span class="mx-2">•</span>
                <span class="text-purple-600">Average: {{ number_format($stats['match_similarity']['average_score'], 2) }}%</span>
            </div>
        </div>

        <!-- Total Charges Collected -->
        @if(auth('admin')->user()->isSuperAdmin() || auth('admin')->user()->role === 'admin')
        <div class="bg-gradient-to-br from-orange-50 to-amber-50 rounded-lg shadow-sm p-6 border-2 border-orange-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Charges Collected</p>
                    <h3 class="text-2xl font-bold text-gray-900">₦{{ number_format($stats['charges']['total_collected'] ?? 0, 2) }}</h3>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-percent text-orange-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center text-sm">
                <span class="text-gray-600">Today: ₦{{ number_format($stats['charges']['today_collected'] ?? 0, 2) }}</span>
            </div>
            <div class="mt-3 pt-3 border-t border-orange-200">
                <div class="text-xs text-gray-500">
                    <div class="mb-1">
                        <i class="fas fa-check-circle text-green-500 mr-1"></i>
                        Enabled: <span class="font-semibold text-gray-900">{{ number_format($stats['charges']['websites_with_charges_enabled'] ?? 0) }}</span>
                    </div>
                    <div>
                        <i class="fas fa-times-circle text-red-500 mr-1"></i>
                        Disabled: <span class="font-semibold text-gray-900">{{ number_format($stats['charges']['websites_with_charges_disabled'] ?? 0) }}</span>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Unmatched Pending Transactions Widget -->
    <div class="bg-gradient-to-r from-yellow-50 to-orange-50 rounded-lg shadow-sm border-2 border-yellow-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Unmatched Pending Transactions</h3>
                    <p class="text-sm text-gray-600">Transactions waiting for payment email match</p>
                </div>
            </div>
            @if($stats['unmatched_pending']['total'] > 0)
                <span class="px-3 py-1 text-sm font-medium bg-yellow-100 text-yellow-800 rounded-full">
                    <i class="fas fa-clock mr-1"></i> {{ $stats['unmatched_pending']['total'] }} Pending
                </span>
            @else
                <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">
                    <i class="fas fa-check-circle mr-1"></i> All Matched
                </span>
            @endif
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg p-4 border border-yellow-100">
                <p class="text-xs text-gray-500 mb-1">Total Unmatched</p>
                <p class="text-2xl font-bold text-yellow-600">{{ number_format($stats['unmatched_pending']['total']) }}</p>
                <p class="text-xs text-gray-500 mt-1">pending transactions</p>
            </div>
            <div class="bg-white rounded-lg p-4 border border-yellow-100">
                <p class="text-xs text-gray-500 mb-1">Expiring Soon</p>
                <p class="text-2xl font-bold text-orange-600">{{ number_format($stats['unmatched_pending']['expiring_soon']) }}</p>
                <p class="text-xs text-gray-500 mt-1">next 2 hours</p>
            </div>
            <div class="bg-white rounded-lg p-4 border border-yellow-100">
                <p class="text-xs text-gray-500 mb-1">Recent</p>
                <p class="text-sm font-medium text-gray-900">{{ $stats['unmatched_pending']['recent']->count() }} transactions</p>
                <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}" class="text-xs text-yellow-600 hover:text-yellow-800 mt-1 inline-block">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        
        @if($stats['unmatched_pending']['recent']->count() > 0)
        <div class="mt-4 pt-4 border-t border-yellow-200">
            <h4 class="text-sm font-semibold text-gray-900 mb-3">Recent Unmatched Transactions</h4>
            <div class="space-y-2 max-h-64 overflow-y-auto">
                @foreach($stats['unmatched_pending']['recent']->take(5) as $payment)
                <div class="bg-white rounded-lg p-3 border border-yellow-100 hover:border-yellow-200">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <a href="{{ route('admin.payments.show', $payment) }}" class="text-sm font-medium text-primary hover:underline">
                                {{ $payment->transaction_id }}
                            </a>
                            <div class="flex items-center gap-4 mt-1 text-xs text-gray-600">
                                <span>₦{{ number_format($payment->amount, 2) }}</span>
                                @if($payment->payer_name)
                                    <span>{{ Str::limit($payment->payer_name, 30) }}</span>
                                @endif
                                @if($payment->expires_at)
                                    <span class="text-xs {{ $payment->expires_at->diffInHours(now()) < 2 ? 'text-red-600' : 'text-gray-600' }}">
                                        <i class="fas fa-clock mr-1"></i>Expires {{ $payment->expires_at->diffForHumans() }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('admin.match-attempts.index', ['transaction_id' => $payment->transaction_id]) }}" 
                           class="text-xs text-yellow-600 hover:text-yellow-800 ml-4" title="View Match Attempts">
                            <i class="fas fa-search-dollar"></i>
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <!-- Email Monitoring Actions -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Email Monitoring</h3>
                <p class="text-sm text-gray-600 mt-1">Manually trigger email fetching or check for transaction updates</p>
            </div>
        </div>
        <div class="flex items-center gap-4 flex-wrap">
            <button onclick="fetchEmails()" id="fetch-emails-btn" 
                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                <i class="fas fa-envelope mr-2"></i> Fetch Emails (IMAP)
            </button>
            <button onclick="fetchEmailsDirect()" id="fetch-emails-direct-btn" 
                class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 flex items-center">
                <i class="fas fa-folder-open mr-2"></i> Fetch Emails (Direct)
            </button>
            <button onclick="checkTransactionUpdates()" id="check-updates-btn"
                class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 flex items-center">
                <i class="fas fa-sync-alt mr-2"></i> Check Transaction Updates
            </button>
        </div>
        <div id="monitoring-result" class="mt-4 hidden"></div>
    </div>

    <!-- Cron Job Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-blue-900 mb-2">
            <i class="fas fa-clock mr-2"></i> Cron Job URLs
        </h3>
        <p class="text-sm text-blue-800 mb-4">
            Use these URLs in your cron job service (e.g., cron-job.org, EasyCron) to automatically process emails and match payments:
        </p>
        
        <div class="space-y-4">
            <!-- Direct Filesystem Reading (Recommended) -->
            <div class="bg-white rounded-lg p-4 border border-green-300">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <span class="text-xs font-semibold text-green-700 bg-green-100 px-2 py-1 rounded">RECOMMENDED</span>
                        <span class="text-sm font-medium text-gray-900 ml-2">Direct Filesystem Reading</span>
                    </div>
                    <button onclick="copyCronUrl('direct')" class="text-green-600 hover:text-green-800 text-sm">
                        <i class="fas fa-copy mr-1"></i> Copy
                    </button>
                </div>
                <code class="text-xs text-gray-700 break-all block bg-gray-50 p-2 rounded">{{ url('/cron/read-emails-direct') }}</code>
                <p class="text-xs text-gray-600 mt-2">
                    <strong>Best for shared hosting.</strong> Reads emails directly from server mail directories (bypasses IMAP).
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    <strong>Frequency:</strong> Every 5-15 minutes
                </p>
            </div>

            <!-- Global Match -->
            <div class="bg-white rounded-lg p-4 border border-purple-300">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <span class="text-xs font-semibold text-purple-700 bg-purple-100 px-2 py-1 rounded">MATCHING</span>
                        <span class="text-sm font-medium text-gray-900 ml-2">Global Match</span>
                    </div>
                    <button onclick="copyCronUrl('global-match')" class="text-purple-600 hover:text-purple-800 text-sm">
                        <i class="fas fa-copy mr-1"></i> Copy
                    </button>
                </div>
                <code class="text-xs text-gray-700 break-all block bg-gray-50 p-2 rounded">{{ url('/cron/global-match') }}</code>
                <p class="text-xs text-gray-600 mt-2">
                    <strong>Matches unmatched payments with unmatched emails.</strong> Runs the global matching process using the new matching logic with full logging.
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    <strong>Frequency:</strong> Every 10-30 minutes (can be less frequent than email reading)
                </p>
            </div>

            <!-- Extract Missing Names -->
            <div class="bg-white rounded-lg p-4 border border-orange-300">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <span class="text-xs font-semibold text-orange-700 bg-orange-100 px-2 py-1 rounded">EXTRACTION</span>
                        <span class="text-sm font-medium text-gray-900 ml-2">Extract Missing Names</span>
                    </div>
                    <button onclick="copyCronUrl('extract-names')" class="text-orange-600 hover:text-orange-800 text-sm">
                        <i class="fas fa-copy mr-1"></i> Copy
                    </button>
                </div>
                <code class="text-xs text-gray-700 break-all block bg-gray-50 p-2 rounded">{{ url('/cron/extract-missing-names') }}</code>
                <p class="text-xs text-gray-600 mt-2">
                    <strong>Extracts sender names from processed emails using AdvancedNameExtractor.</strong> Handles multiple formats, quoted-printable encoding, and spacing variations.
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    <strong>Frequency:</strong> Every 5-10 minutes (can run less frequently than email reading)
                </p>
            </div>

            <!-- IMAP Email Fetching -->
            <div class="bg-white rounded-lg p-4 border border-blue-300">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <span class="text-sm font-medium text-gray-900">IMAP Email Fetching</span>
                    </div>
                    <button onclick="copyCronUrl('imap')" class="text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fas fa-copy mr-1"></i> Copy
                    </button>
                </div>
                <code class="text-xs text-gray-700 break-all block bg-gray-50 p-2 rounded">{{ url('/cron/monitor-emails') }}</code>
                <p class="text-xs text-gray-600 mt-2">
                    Requires IMAP to be enabled. Will fail if IMAP fetching is disabled in settings.
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    <strong>Frequency:</strong> Every 5-15 minutes
                </p>
            </div>
        </div>

        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
            <p class="text-xs text-yellow-800 font-semibold mb-2">
                <i class="fas fa-info-circle mr-1"></i> Recommended Setup:
            </p>
            <ul class="text-xs text-yellow-700 space-y-1 list-disc list-inside">
                <li><strong>Email Reading:</strong> Every 5-15 minutes (Direct Filesystem or IMAP)</li>
                <li><strong>Extract Missing Names:</strong> Every 5-10 minutes (extracts sender names from emails)</li>
                <li><strong>Global Matching:</strong> Every 10-30 minutes (matches unmatched items)</li>
            </ul>
            <p class="text-xs text-yellow-700 mt-2">
                <strong>Note:</strong> If IMAP is disabled, only use the "Direct Filesystem Reading" URL above.
            </p>
        </div>
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
                                @if($email->source === 'gmail_api')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 mt-1">
                                    <i class="fas fa-google mr-1"></i> Gmail API
                                </span>
                            @elseif($email->source === 'imap')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mt-1">
                                    <i class="fas fa-server mr-1"></i> IMAP
                                </span>
                            @elseif($email->source === 'direct_filesystem')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 mt-1">
                                    <i class="fas fa-file-alt mr-1"></i> Direct
                                </span>
                            @endif
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
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
                            <td class="px-6 py-4">
                                @if($payment->status === 'approved')
                                    <button onclick="resendWebhook({{ $payment->id }})" 
                                            id="resend-webhook-btn-{{ $payment->id }}"
                                            class="px-3 py-1 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-xs flex items-center"
                                            title="Resend webhook notification">
                                        <i class="fas fa-paper-plane mr-1"></i> Resend Webhook
                                    </button>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No payments found</td>
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
    
    if (!btn || !resultDiv) {
        console.error('Required elements not found');
        alert('Error: Button or result div not found. Please refresh the page.');
        return;
    }
    
    const originalText = btn.innerHTML;
    const csrfToken = getCsrfToken();
    
    if (!csrfToken) {
        alert('Error: CSRF token not found. Please refresh the page.');
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Fetching (IMAP)...';
    resultDiv.classList.add('hidden');
    
    fetch('{{ route("admin.email-monitor.fetch") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            resultDiv.className = 'mt-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg';
            
            let content = '<strong>Success!</strong> ' + data.message;
            
            // Display stats if available
            if (data.stats) {
                content += '<div class="mt-3 grid grid-cols-3 gap-4 text-sm">';
                content += '<div><strong>Processed:</strong> ' + (data.stats.processed || 0) + '</div>';
                content += '<div><strong>Skipped:</strong> ' + (data.stats.skipped || 0) + '</div>';
                content += '<div><strong>Failed:</strong> ' + (data.stats.failed || 0) + '</div>';
                content += '</div>';
            }
            
            // Display summary if available (preferred over full output)
            if (data.summary) {
                content += '<pre class="mt-2 text-xs overflow-auto max-h-40 bg-white p-2 rounded border">' + (data.summary || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</pre>';
            } else if (data.output) {
                // Fallback to truncated output if summary not available
                content += '<pre class="mt-2 text-xs overflow-auto max-h-40 bg-white p-2 rounded border">' + (data.output || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</pre>';
            }
            
            resultDiv.innerHTML = content;
        } else {
            resultDiv.className = 'mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg';
            resultDiv.innerHTML = '<strong>Error!</strong> ' + (data.message || 'Unknown error');
        }
        resultDiv.classList.remove('hidden');
    })
    .catch(error => {
        console.error('Error fetching emails:', error);
        resultDiv.className = 'mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg';
        resultDiv.innerHTML = '<strong>Error!</strong> ' + error.message;
        resultDiv.classList.remove('hidden');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function fetchEmailsDirect() {
    const btn = document.getElementById('fetch-emails-direct-btn');
    const resultDiv = document.getElementById('monitoring-result');
    
    if (!btn || !resultDiv) {
        console.error('Required elements not found');
        alert('Error: Button or result div not found. Please refresh the page.');
        return;
    }
    
    const originalText = btn.innerHTML;
    const csrfToken = getCsrfToken();
    
    if (!csrfToken) {
        alert('Error: CSRF token not found. Please refresh the page.');
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Reading...';
    resultDiv.classList.add('hidden');
    
    fetch('{{ route("admin.email-monitor.fetch-direct") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            resultDiv.className = 'mt-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg';
            
            let content = '<strong>Success!</strong> ' + data.message;
            
            // Display stats if available
            if (data.stats) {
                content += '<div class="mt-3 grid grid-cols-3 gap-4 text-sm">';
                content += '<div><strong>Processed:</strong> ' + (data.stats.processed || 0) + '</div>';
                content += '<div><strong>Skipped:</strong> ' + (data.stats.skipped || 0) + '</div>';
                content += '<div><strong>Failed:</strong> ' + (data.stats.failed || 0) + '</div>';
                content += '</div>';
            }
            
            // Display summary if available (preferred over full output)
            if (data.summary) {
                content += '<pre class="mt-2 text-xs overflow-auto max-h-40 bg-white p-2 rounded border">' + (data.summary || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</pre>';
            } else if (data.output) {
                // Fallback to truncated output if summary not available
                content += '<pre class="mt-2 text-xs overflow-auto max-h-40 bg-white p-2 rounded border">' + (data.output || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</pre>';
            }
            
            resultDiv.innerHTML = content;
        } else {
            resultDiv.className = 'mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg';
            resultDiv.innerHTML = '<strong>Error!</strong> ' + (data.message || 'Unknown error');
        }
        resultDiv.classList.remove('hidden');
    })
    .catch(error => {
        console.error('Error fetching emails direct:', error);
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
    
    if (!btn || !resultDiv) {
        console.error('Required elements not found');
        alert('Error: Button or result div not found. Please refresh the page.');
        return;
    }
    
    const originalText = btn.innerHTML;
    const csrfToken = getCsrfToken();
    
    if (!csrfToken) {
        alert('Error: CSRF token not found. Please refresh the page.');
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Checking...';
    resultDiv.classList.add('hidden');
    
    fetch('{{ route("admin.email-monitor.check-updates") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
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
            resultDiv.innerHTML = '<strong>Error!</strong> ' + (data.message || 'Unknown error');
        }
        resultDiv.classList.remove('hidden');
    })
    .catch(error => {
        console.error('Error checking transaction updates:', error);
        resultDiv.className = 'mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg';
        resultDiv.innerHTML = '<strong>Error!</strong> ' + error.message;
        resultDiv.classList.remove('hidden');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function copyCronUrl(type = 'direct') {
    let url;
    
    if (type === 'direct') {
        url = '{{ url('/cron/read-emails-direct') }}';
    } else if (type === 'imap') {
        url = '{{ url('/cron/monitor-emails') }}';
    } else if (type === 'global-match') {
        url = '{{ url('/cron/global-match') }}';
    } else if (type === 'extract-names') {
        url = '{{ url('/cron/extract-missing-names') }}';
    } else {
        url = '{{ url('/cron/read-emails-direct') }}';
    }
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
            // Show temporary success message
            const message = document.createElement('div');
            message.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
            message.textContent = 'Cron URL copied to clipboard!';
            document.body.appendChild(message);
            
            setTimeout(() => {
                message.remove();
            }, 2000);
        }).catch(function(err) {
            console.error('Clipboard error:', err);
            alert('Failed to copy URL. Please copy manually: ' + url);
        });
    } else {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = url;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Cron URL copied to clipboard!');
    }
}

function extractMissingNames() {
    const btn = document.getElementById('extract-names-btn');
    if (!btn) {
        console.error('Extract names button not found');
        alert('Error: Button not found. Please refresh the page.');
        return;
    }
    
    const originalHTML = btn.innerHTML;
    
    if (!confirm('This will extract missing sender names from processed emails using the description field pattern. This may take a while. Continue?')) {
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Extracting Names...';
    
    // Get CSRF token
    const csrfToken = getCsrfToken();
    if (!csrfToken) {
        alert('Error: CSRF token not found. Please refresh the page.');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        return;
    }
    
    fetch('{{ route("admin.extract-missing-names") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            limit: 50
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message + '\n\nCheck the console for detailed output.');
            if (data.output) {
                console.log('Extraction Output:', data.output);
            }
            // Reload page to show updated stats
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            alert('❌ ' + data.message);
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error extracting names: ' + error.message);
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

function triggerGlobalMatch() {
    const btn = document.getElementById('global-match-btn');
    if (!btn) {
        console.error('Global match button not found');
        alert('Error: Button not found. Please refresh the page.');
        return;
    }
    
    const originalHTML = btn.innerHTML;
    
    if (!confirm('This will check all unmatched pending payments against all unmatched emails using the new matching logic with full logging. This may take a while. Continue?')) {
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Running Match...';
    
    // Get CSRF token with error handling
    const csrfToken = getCsrfToken();
    if (!csrfToken) {
        alert('Error: CSRF token not found. Please refresh the page.');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        return;
    }
    
    fetch('{{ route("admin.match.trigger-global") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            let message = '✅ ' + data.message + '\n\n';
            
            if (data.results && data.results.matches_found > 0) {
                message += 'Matches Found: ' + data.results.matches_found + '\n';
                
                if (data.results.matched_emails && data.results.matched_emails.length > 0) {
                    message += '\nFrom Emails:\n';
                    data.results.matched_emails.forEach(match => {
                        message += `  • Email #${match.email_id} → Transaction ${match.transaction_id}\n`;
                    });
                }
                
                if (data.results.matched_payments && data.results.matched_payments.length > 0) {
                    message += '\nFrom Payments:\n';
                    data.results.matched_payments.forEach(match => {
                        message += `  • Transaction ${match.transaction_id} → Email #${match.email_id}\n`;
                    });
                }
            }
            
            if (data.results && data.results.errors && data.results.errors.length > 0) {
                message += `\n\n⚠️ Errors: ${data.results.errors.length} error(s) occurred. Check logs for details.`;
            }
            
            alert(message);
            
            // Reload page after 2 seconds to show updated stats
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            alert('❌ Error: ' + (data.message || 'Unknown error occurred'));
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        console.error('Error triggering global match:', error);
        alert('❌ Error triggering global match: ' + error.message);
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

// Helper function to get CSRF token
function getCsrfToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag) {
        return metaTag.getAttribute('content');
    }
    console.error('CSRF token meta tag not found');
    return null;
}

function resendWebhook(paymentId) {
    const btn = document.getElementById('resend-webhook-btn-' + paymentId);
    if (!btn) return;
    
    if (!confirm('Are you sure you want to resend the webhook notification to the business?')) {
        return;
    }
    
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Sending...';
    
    const csrfToken = getCsrfToken();
    if (!csrfToken) {
        alert('Error: CSRF token not found. Please refresh the page.');
        btn.disabled = false;
        btn.innerHTML = originalText;
        return;
    }
    
    fetch(`/admin/payments/${paymentId}/resend-webhook`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
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

</script>
@endsection






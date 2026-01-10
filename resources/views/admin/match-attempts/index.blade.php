@extends('layouts.admin')

@section('title', 'Match Attempts Log')
@section('page-title', 'Match Attempts Log')

@section('content')
<div class="space-y-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Attempts</p>
                    <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['total']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-search-dollar text-blue-600 text-xl"></i>
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
                    <i class="fas fa-times-circle text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Today</p>
                    <h3 class="text-2xl font-bold text-purple-600">{{ number_format($stats['today']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-calendar-day text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Common Failure Reasons -->
    @if($commonReasons->count() > 0)
    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>Common Failure Reasons
        </h3>
        <div class="space-y-2">
            @foreach($commonReasons as $reason)
            <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                <div class="flex-1">
                    <p class="text-sm text-gray-900">{{ Str::limit($reason->reason_short, 150) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Occurred {{ number_format($reason->count) }} time(s)</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
        <form method="GET" action="{{ route('admin.match-attempts.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Match Result</label>
                <select name="result" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="">All Results</option>
                    <option value="matched" {{ request('result') === 'matched' ? 'selected' : '' }}>Matched</option>
                    <option value="unmatched" {{ request('result') === 'unmatched' ? 'selected' : '' }}>Unmatched</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Extraction Method</label>
                <select name="extraction_method" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="">All Methods</option>
                    <option value="html_table" {{ request('extraction_method') === 'html_table' ? 'selected' : '' }}>HTML Table</option>
                    <option value="html_text" {{ request('extraction_method') === 'html_text' ? 'selected' : '' }}>HTML Text</option>
                    <option value="rendered_text" {{ request('extraction_method') === 'rendered_text' ? 'selected' : '' }}>Rendered Text</option>
                    <option value="template" {{ request('extraction_method') === 'template' ? 'selected' : '' }}>Template</option>
                    <option value="fallback" {{ request('extraction_method') === 'fallback' ? 'selected' : '' }}>Fallback</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Transaction ID</label>
                <input type="text" name="transaction_id" value="{{ request('transaction_id') }}" 
                    placeholder="Search transaction ID..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email ID</label>
                <input type="number" name="processed_email_id" value="{{ request('processed_email_id') }}" 
                    placeholder="Filter by email ID..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>

            <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Search Reason</label>
                <input type="text" name="reason_search" value="{{ request('reason_search') }}" 
                    placeholder="Search in reason text..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>

            <div class="md:col-span-6 flex justify-end gap-4">
                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
                <a href="{{ route('admin.match-attempts.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    <i class="fas fa-times mr-2"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Match Attempts Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Match Attempts</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Result</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Similarity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($attempts as $attempt)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            @if($attempt->payment)
                                <a href="{{ route('admin.payments.show', $attempt->payment) }}" class="text-sm font-medium text-primary hover:underline">
                                    {{ $attempt->transaction_id ?? '-' }}
                                </a>
                            @else
                                <span class="text-sm text-gray-600">{{ $attempt->transaction_id ?? '-' }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($attempt->match_result === 'matched')
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                    <i class="fas fa-check-circle mr-1"></i> Matched
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                    <i class="fas fa-times-circle mr-1"></i> Unmatched
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="text-gray-900">
                                Payment: ₦{{ number_format($attempt->payment_amount ?? 0, 2) }}
                            </div>
                            <div class="text-gray-600 text-xs mt-1">
                                Extracted: ₦{{ number_format($attempt->extracted_amount ?? 0, 2) }}
                            </div>
                            @if($attempt->amount_diff !== null)
                                <div class="text-xs mt-1 {{ $attempt->amount_diff > 0 ? 'text-red-600' : 'text-green-600' }}">
                                    Diff: ₦{{ number_format(abs($attempt->amount_diff), 2) }}
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="text-gray-900">
                                Payment: {{ Str::limit($attempt->payment_name ?? '-', 20) }}
                            </div>
                            <div class="text-gray-600 text-xs mt-1">
                                Extracted: {{ Str::limit($attempt->extracted_name ?? '-', 20) }}
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded">
                                {{ $attempt->extraction_method ?? 'unknown' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @if($attempt->name_similarity_percent !== null)
                                <div class="flex items-center">
                                    <span class="font-medium {{ $attempt->name_similarity_percent >= 65 ? 'text-green-600' : 'text-yellow-600' }}">
                                        {{ $attempt->name_similarity_percent }}%
                                    </span>
                                    <div class="ml-2 w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full {{ $attempt->name_similarity_percent >= 65 ? 'bg-green-600' : 'bg-yellow-600' }}" 
                                             style="width: {{ $attempt->name_similarity_percent }}%"></div>
                                    </div>
                                </div>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 max-w-xs">
                            <div class="truncate" title="{{ $attempt->reason }}">
                                {{ Str::limit($attempt->reason, 60) }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $attempt->created_at->format('M d, H:i') }}
                            @if($attempt->time_diff_minutes !== null)
                                <div class="text-xs text-gray-400 mt-1">
                                    {{ $attempt->time_diff_minutes }}m
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.match-attempts.show', $attempt) }}" 
                                   class="text-sm text-primary hover:underline" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if($attempt->match_result === 'unmatched' && $attempt->payment)
                                    <button onclick="retryMatch({{ $attempt->id }})" 
                                            class="text-sm text-green-600 hover:text-green-800" 
                                            title="Retry Match">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">
                            No match attempts found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-6 border-t border-gray-200">
            {{ $attempts->links() }}
        </div>
    </div>
</div>

<script>
function retryMatch(attemptId) {
    if (!confirm('Are you sure you want to retry matching this transaction?')) {
        return;
    }

    fetch(`/admin/match-attempts/${attemptId}/retry`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            if (data.payment) {
                window.location.reload();
            }
        } else {
            alert('❌ ' + data.message);
            if (data.latest_reason) {
                console.log('Latest reason:', data.latest_reason);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error retrying match: ' + error.message);
    });
}
</script>
@endsection

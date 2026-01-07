@extends('layouts.admin')

@section('title', 'Zapier Logs')
@section('page-title', 'Zapier Logs')

@section('content')
<div class="space-y-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Logs</p>
                    <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['total']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-bolt text-purple-600 text-xl"></i>
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
                    <p class="text-sm text-gray-600 mb-1">Errors</p>
                    <h3 class="text-2xl font-bold text-red-600">{{ number_format($stats['error']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Today</p>
                    <h3 class="text-2xl font-bold text-blue-600">{{ number_format($stats['today']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-calendar-day text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form method="GET" action="{{ route('admin.zapier-logs.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" id="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="">All Statuses</option>
                    <option value="received" {{ request('status') === 'received' ? 'selected' : '' }}>Received</option>
                    <option value="processed" {{ request('status') === 'processed' ? 'selected' : '' }}>Processed</option>
                    <option value="matched" {{ request('status') === 'matched' ? 'selected' : '' }}>Matched</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    <option value="error" {{ request('status') === 'error' ? 'selected' : '' }}>Error</option>
                </select>
            </div>

            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>

            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>

            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Email, sender name..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>

            <div class="md:col-span-4 flex justify-end gap-2">
                <a href="{{ route('admin.zapier-logs.index') }}" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Clear</a>
                <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90">Filter</button>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sender Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sender Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($zapierLogs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $log->created_at->format('M d, Y H:i:s') }}
                            <div class="text-xs text-gray-400">{{ $log->created_at->diffForHumans() }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <code class="bg-gray-100 px-2 py-1 rounded text-xs">{{ $log->extracted_from_email ?? '-' }}</code>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $log->sender_name ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            @if($log->amount)
                                ₦{{ number_format($log->amount, 2) }}
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($log->status === 'matched')
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Matched</span>
                            @elseif($log->status === 'processed')
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">Processed</span>
                            @elseif($log->status === 'rejected')
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">Rejected</span>
                            @elseif($log->status === 'error')
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Error</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">Received</span>
                            @endif
                            @if($log->status_message)
                                <div class="text-xs text-gray-500 mt-1">{{ Str::limit($log->status_message, 50) }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @if($log->payment)
                                <a href="{{ route('admin.payments.show', $log->payment) }}" class="text-primary hover:underline">
                                    {{ $log->payment->transaction_id }}
                                </a>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.zapier-logs.show', $log) }}" class="text-sm text-primary hover:underline">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                                @if(in_array($log->status, ['error', 'rejected', 'no_match', 'processed']))
                                    <button onclick="retryZapierLog({{ $log->id }})" class="text-sm text-green-600 hover:text-green-800">
                                        <i class="fas fa-redo mr-1"></i> Retry
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                            <div class="py-8">
                                <i class="fas fa-bolt text-gray-400 text-4xl mb-3"></i>
                                <p class="text-gray-600">No Zapier logs found</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($zapierLogs->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $zapierLogs->links() }}
            </div>
        @endif
    </div>
</div>

<script>
function retryZapierLog(logId) {
    if (!confirm('Are you sure you want to retry processing this Zapier log?')) {
        return;
    }

    fetch(`/admin/zapier-logs/${logId}/retry`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Zapier log reprocessed successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while retrying the Zapier log');
    });
}
</script>
@endsection

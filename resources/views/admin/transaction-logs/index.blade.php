@extends('layouts.admin')

@section('title', 'Transaction Logs')
@section('page-title', 'Transaction Logs')

@section('content')
<div class="space-y-6">
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" class="flex items-center space-x-4 flex-wrap">
            <input type="text" name="transaction_id" value="{{ request('transaction_id') }}" 
                placeholder="Transaction ID" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <select name="event_type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Events</option>
                <option value="payment_requested" {{ request('event_type') === 'payment_requested' ? 'selected' : '' }}>Payment Requested</option>
                <option value="account_assigned" {{ request('event_type') === 'account_assigned' ? 'selected' : '' }}>Account Assigned</option>
                <option value="email_received" {{ request('event_type') === 'email_received' ? 'selected' : '' }}>Email Received</option>
                <option value="payment_matched" {{ request('event_type') === 'payment_matched' ? 'selected' : '' }}>Payment Matched</option>
                <option value="payment_approved" {{ request('event_type') === 'payment_approved' ? 'selected' : '' }}>Payment Approved</option>
                <option value="payment_rejected" {{ request('event_type') === 'payment_rejected' ? 'selected' : '' }}>Payment Rejected</option>
                <option value="payment_expired" {{ request('event_type') === 'payment_expired' ? 'selected' : '' }}>Payment Expired</option>
                <option value="webhook_sent" {{ request('event_type') === 'webhook_sent' ? 'selected' : '' }}>Webhook Sent</option>
                <option value="webhook_failed" {{ request('event_type') === 'webhook_failed' ? 'selected' : '' }}>Webhook Failed</option>
                <option value="withdrawal_requested" {{ request('event_type') === 'withdrawal_requested' ? 'selected' : '' }}>Withdrawal Requested</option>
                <option value="withdrawal_approved" {{ request('event_type') === 'withdrawal_approved' ? 'selected' : '' }}>Withdrawal Approved</option>
                <option value="withdrawal_rejected" {{ request('event_type') === 'withdrawal_rejected' ? 'selected' : '' }}>Withdrawal Rejected</option>
                <option value="withdrawal_processed" {{ request('event_type') === 'withdrawal_processed' ? 'selected' : '' }}>Withdrawal Processed</option>
            </select>
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
            <button type="submit" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 text-sm">Filter</button>
            <a href="{{ route('admin.transaction-logs.index') }}" class="text-gray-600 hover:text-gray-900 text-sm">Clear</a>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Timestamp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.transaction-logs.show', $log->transaction_id) }}" class="text-sm font-medium text-primary hover:underline">
                                {{ $log->transaction_id }}
                            </a>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                {{ str_replace('_', ' ', ucwords($log->event_type, '_')) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $log->description }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $log->business->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $log->created_at->format('M d, Y H:i:s') }}</td>
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.transaction-logs.show', $log->transaction_id) }}" class="text-primary hover:underline text-sm">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No transaction logs found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

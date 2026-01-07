@extends('layouts.admin')

@section('title', 'Business Details')
@section('page-title', 'Business Details')

@section('content')
<div class="space-y-6">
    <!-- Business Info -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">{{ $business->name }}</h3>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.businesses.edit', $business) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Edit
                </a>
                @if($business->is_active)
                    <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                @else
                    <span class="px-3 py-1 text-sm font-medium bg-gray-100 text-gray-800 rounded-full">Inactive</span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6">
            <div>
                <label class="text-sm text-gray-600">Email</label>
                <p class="text-sm font-medium text-gray-900">{{ $business->email }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Phone</label>
                <p class="text-sm font-medium text-gray-900">{{ $business->phone ?? 'N/A' }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Balance</label>
                <p class="text-lg font-bold text-gray-900">₦{{ number_format($business->balance, 2) }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Webhook URL</label>
                <p class="text-sm font-medium text-gray-900">{{ $business->webhook_url ?? 'N/A' }}</p>
            </div>
        </div>

        <div class="mt-6 pt-6 border-t border-gray-200">
            <label class="text-sm text-gray-600 mb-2 block">API Key</label>
            <div class="flex items-center space-x-2">
                <input type="text" value="{{ $business->api_key }}" readonly
                    class="flex-1 border border-gray-300 rounded-lg px-3 py-2 bg-gray-50 text-sm font-mono">
                <form action="{{ route('admin.businesses.regenerate-api-key', $business) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-yellow-100 text-yellow-800 rounded-lg hover:bg-yellow-200 text-sm"
                        onclick="return confirm('Are you sure? This will invalidate the current API key.')">
                        Regenerate
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Account Numbers -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Account Numbers</h3>
        @if($business->accountNumbers->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Account Number</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Account Name</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bank</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Usage</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($business->accountNumbers as $account)
                        <tr>
                            <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $account->account_number }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $account->account_name }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $account->bank_name }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $account->usage_count }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500">No account numbers assigned. System will use pool accounts.</p>
        @endif
    </div>

    <!-- Recent Payments -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Payments</h3>
        @if($business->payments->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($business->payments->take(10) as $payment)
                        <tr>
                            <td class="px-4 py-2 text-sm">
                                <a href="{{ route('admin.payments.show', $payment) }}" class="text-primary hover:underline">
                                    {{ $payment->transaction_id }}
                                </a>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-900">₦{{ number_format($payment->amount, 2) }}</td>
                            <td class="px-4 py-2">
                                @if($payment->status === 'approved')
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                                @elseif($payment->status === 'pending')
                                    <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $payment->created_at->format('M d, Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500">No payments yet.</p>
        @endif
    </div>
</div>
@endsection

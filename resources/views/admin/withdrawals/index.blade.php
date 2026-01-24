@extends('layouts.admin')

@section('title', 'Withdrawals')
@section('page-title', 'Withdrawals')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Withdrawals</h2>
            <p class="text-sm text-gray-600 mt-1">Manage withdrawal requests</p>
        </div>
        <a href="{{ route('admin.withdrawals.create') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 text-sm font-medium">
            <i class="fas fa-plus mr-2"></i> Create Withdrawal
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" class="flex items-center space-x-4 flex-wrap">
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Status</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                <option value="processed" {{ request('status') === 'processed' ? 'selected' : '' }}>Processed</option>
            </select>
            <select name="business_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Businesses</option>
                @foreach(\App\Models\Business::all() as $business)
                    <option value="{{ $business->id }}" {{ request('business_id') == $business->id ? 'selected' : '' }}>
                        {{ $business->name }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 text-sm">Filter</button>
            <a href="{{ route('admin.withdrawals.index') }}" class="text-gray-600 hover:text-gray-900 text-sm">Clear</a>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($withdrawals as $withdrawal)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $withdrawal->business->name }}</td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">₦{{ number_format($withdrawal->amount, 2) }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            <div>{{ $withdrawal->account_name }}</div>
                            <div class="text-xs text-gray-500">{{ $withdrawal->account_number }} - {{ $withdrawal->bank_name }}</div>
                        </td>
                        <td class="px-6 py-4">
                            @if($withdrawal->status === 'approved')
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                            @elseif($withdrawal->status === 'pending')
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                            @elseif($withdrawal->status === 'rejected')
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">Processed</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $withdrawal->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.withdrawals.show', $withdrawal) }}" class="text-primary hover:underline text-sm">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No withdrawals found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($withdrawals->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $withdrawals->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

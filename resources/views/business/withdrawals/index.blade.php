@extends('layouts.business')

@section('title', 'Withdrawals')
@section('page-title', 'Withdrawals')

@section('content')
<div class="space-y-4 lg:space-y-6 pb-20 lg:pb-0">
    <!-- Header Actions -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Withdrawal Requests</h3>
            <p class="text-xs lg:text-sm text-gray-600 mt-1">Manage your withdrawal requests</p>
        </div>
        <a href="{{ route('business.withdrawals.create') }}" class="w-full sm:w-auto px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium text-center">
            <i class="fas fa-plus mr-2"></i> Request Withdrawal
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6">
        <form method="GET" action="{{ route('business.withdrawals.index') }}" class="flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
                <label class="block text-xs lg:text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    <option value="">All</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    <option value="processed" {{ request('status') === 'processed' ? 'selected' : '' }}>Processed</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Withdrawals List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <!-- Desktop Table View -->
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bank</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($withdrawals as $withdrawal)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">₦{{ number_format($withdrawal->amount, 2) }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $withdrawal->bank_name }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            <div>{{ $withdrawal->account_name }}</div>
                            <div class="text-xs text-gray-500">{{ $withdrawal->account_number }}</div>
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
                            <a href="{{ route('business.withdrawals.show', $withdrawal) }}" class="text-sm text-primary hover:underline">
                                View
                            </a>
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
        
        <!-- Mobile Card View -->
        <div class="lg:hidden divide-y divide-gray-200">
            @forelse($withdrawals as $withdrawal)
            <a href="{{ route('business.withdrawals.show', $withdrawal) }}" class="block p-4 hover:bg-gray-50 transition-colors">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-base font-bold text-gray-900 mb-1">₦{{ number_format($withdrawal->amount, 2) }}</p>
                        <p class="text-xs text-gray-500">{{ $withdrawal->created_at->format('M d, Y') }}</p>
                    </div>
                    <div class="ml-3">
                        @if($withdrawal->status === 'approved')
                            <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                        @elseif($withdrawal->status === 'pending')
                            <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                        @elseif($withdrawal->status === 'rejected')
                            <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                        @else
                            <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">Processed</span>
                        @endif
                    </div>
                </div>
                <div class="space-y-2 pt-3 border-t border-gray-100">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-600">Bank:</span>
                        <span class="font-medium text-gray-900">{{ $withdrawal->bank_name }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-600">Account:</span>
                        <span class="font-medium text-gray-900">{{ $withdrawal->account_number }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-600">Name:</span>
                        <span class="font-medium text-gray-900 truncate max-w-[150px] ml-2">{{ $withdrawal->account_name }}</span>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <div class="flex items-center justify-end">
                        <span class="text-xs text-primary font-medium">View Details</span>
                        <i class="fas fa-chevron-right text-primary text-xs ml-2"></i>
                    </div>
                </div>
            </a>
            @empty
            <div class="p-8 text-center">
                <i class="fas fa-hand-holding-usd text-gray-300 text-4xl mb-4"></i>
                <p class="text-sm text-gray-500">No withdrawals found</p>
            </div>
            @endforelse
        </div>
        
        <!-- Pagination -->
        <div class="px-4 lg:px-6 py-4 border-t border-gray-200">
            {{ $withdrawals->links() }}
        </div>
    </div>
</div>
@endsection

@extends('layouts.admin')

@section('title', 'Withdrawal Details')
@section('page-title', 'Withdrawal Details')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">Withdrawal Request #{{ $withdrawal->id }}</h3>
            @if($withdrawal->status === 'approved')
                <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
            @elseif($withdrawal->status === 'pending')
                <span class="px-3 py-1 text-sm font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
            @elseif($withdrawal->status === 'rejected')
                <span class="px-3 py-1 text-sm font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
            @else
                <span class="px-3 py-1 text-sm font-medium bg-blue-100 text-blue-800 rounded-full">Processed</span>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-6 mb-6">
            <div>
                <label class="text-sm text-gray-600">Business</label>
                <p class="text-sm font-medium text-gray-900">{{ $withdrawal->business->name }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Amount</label>
                <p class="text-lg font-bold text-gray-900">₦{{ number_format($withdrawal->amount, 2) }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Account Number</label>
                <p class="text-sm font-medium text-gray-900">{{ $withdrawal->account_number }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Account Name</label>
                <p class="text-sm font-medium text-gray-900">{{ $withdrawal->account_name }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Bank Name</label>
                <p class="text-sm font-medium text-gray-900">{{ $withdrawal->bank_name }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Created At</label>
                <p class="text-sm font-medium text-gray-900">{{ $withdrawal->created_at->format('M d, Y H:i:s') }}</p>
            </div>
            @if($withdrawal->processed_at)
            <div>
                <label class="text-sm text-gray-600">Processed At</label>
                <p class="text-sm font-medium text-gray-900">{{ $withdrawal->processed_at->format('M d, Y H:i:s') }}</p>
            </div>
            @endif
            @if($withdrawal->processor)
            <div>
                <label class="text-sm text-gray-600">Processed By</label>
                <p class="text-sm font-medium text-gray-900">{{ $withdrawal->processor->name }}</p>
            </div>
            @endif
        </div>

        @if($withdrawal->rejection_reason)
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <label class="text-sm font-medium text-red-800">Rejection Reason</label>
            <p class="text-sm text-red-700 mt-1">{{ $withdrawal->rejection_reason }}</p>
        </div>
        @endif

        @if($withdrawal->status === 'pending')
        <div class="flex items-center space-x-3 pt-6 border-t border-gray-200">
            <form action="{{ route('admin.withdrawals.approve', $withdrawal) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                    onclick="return confirm('Approve this withdrawal request?')">
                    Approve
                </button>
            </form>
            <button onclick="showRejectModal()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                Reject
            </button>
        </div>

        <!-- Reject Modal -->
        <div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">Reject Withdrawal</h3>
                <form action="{{ route('admin.withdrawals.reject', $withdrawal) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rejection Reason *</label>
                        <textarea name="rejection_reason" rows="3" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
                    </div>
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="hideRejectModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                            Reject
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @elseif($withdrawal->status === 'approved')
        <div class="pt-6 border-t border-gray-200">
            <form action="{{ route('admin.withdrawals.mark-processed', $withdrawal) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                    onclick="return confirm('Mark this withdrawal as processed?')">
                    Mark as Processed
                </button>
            </form>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    function showRejectModal() {
        document.getElementById('rejectModal').classList.remove('hidden');
    }
    function hideRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }
</script>
@endpush
@endsection

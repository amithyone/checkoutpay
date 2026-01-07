@extends('layouts.admin')

@section('title', 'Payments')
@section('page-title', 'Payments')

@section('content')
<div class="space-y-6">
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" class="flex items-center space-x-4 flex-wrap">
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Status</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
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
            <a href="{{ route('admin.payments.index') }}" class="text-gray-600 hover:text-gray-900 text-sm">Clear</a>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payer Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($payments as $payment)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $payment->transaction_id }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $payment->business->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">₦{{ number_format($payment->amount, 2) }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $payment->account_number ?? 'N/A' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $payment->payer_name ?? 'N/A' }}</td>
                        <td class="px-6 py-4">
                            @if($payment->status === 'approved')
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                            @elseif($payment->status === 'pending')
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $payment->created_at->format('M d, Y H:i') }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.payments.show', $payment) }}" class="text-primary hover:underline text-sm">View</a>
                                @if($payment->status === 'pending')
                                    <button onclick="checkMatchForPayment({{ $payment->id }})" 
                                        class="text-sm text-green-600 hover:underline check-match-payment-btn"
                                        data-payment-id="{{ $payment->id }}">
                                        <i class="fas fa-search mr-1"></i> Check Match
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">No payments found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $payments->links() }}
        </div>
        @endif
    </div>
</div>

<script>
function checkMatchForPayment(paymentId) {
    const btn = document.querySelector(`.check-match-payment-btn[data-payment-id="${paymentId}"]`);
    if (!btn) return;
    
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Checking...';
    
    fetch(`/admin/payments/${paymentId}/check-match`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(`HTTP ${response.status}: ${text.substring(0, 200)}`);
            });
        }
        
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
                alert(`✅ Payment matched and approved!\n\nEmail: ${data.email.subject || 'N/A'}\nFrom: ${data.email.from_email || 'N/A'}\nAmount: ₦${data.payment.amount.toLocaleString()}`);
                window.location.reload();
            } else {
                let message = '❌ No matching email found.\n\n';
                if (data.matches && data.matches.length > 0) {
                    message += 'Checked Emails:\n';
                    data.matches.forEach(match => {
                        message += `\n• ${match.email_subject || 'No Subject'}: ${match.reason}`;
                        if (match.time_diff_minutes !== null) {
                            message += ` (${match.time_diff_minutes} min difference)`;
                        }
                    });
                } else {
                    message += 'No unmatched emails found to check against.';
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

@extends('layouts.admin')

@section('title', 'Account Numbers')
@section('page-title', 'Account Numbers')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Manage Account Numbers</h3>
            <p class="text-sm text-gray-600 mt-1">Pool accounts and business-specific accounts</p>
        </div>
        <a href="{{ route('admin.account-numbers.create') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 flex items-center">
            <i class="fas fa-plus mr-2"></i> Add Account Number
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" class="flex items-center space-x-4">
            <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Types</option>
                <option value="pool" {{ request('type') === 'pool' ? 'selected' : '' }}>Regular Pool</option>
                <option value="invoice_pool" {{ request('type') === 'invoice_pool' ? 'selected' : '' }}>Invoice Pool</option>
                <option value="membership_pool" {{ request('type') === 'membership_pool' ? 'selected' : '' }}>Membership Pool</option>
                <option value="tickets_pool" {{ request('type') === 'tickets_pool' ? 'selected' : '' }}>Tickets Pool</option>
                <option value="business" {{ request('type') === 'business' ? 'selected' : '' }}>Business Accounts</option>
            </select>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Status</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            <button type="submit" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 text-sm">Filter</button>
            <a href="{{ route('admin.account-numbers.index') }}" class="text-gray-600 hover:text-gray-900 text-sm">Clear</a>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bank</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase" title="Regular pool (all non-invoice/membership/ticket)">Pool</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase" title="Invoice payments">Invoice</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase" title="Membership payments">Membership</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase" title="Ticket payments">Tickets</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Active</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usage</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payments Received</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($accountNumbers as $account)
                    <tr class="hover:bg-gray-50" data-account-id="{{ $account->id }}" data-update-url="{{ route('admin.account-numbers.update-flags', $account) }}">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $account->account_number }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $account->account_name }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $account->bank_name }}</td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-1">
                                @if($account->is_pool)
                                    <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">Pool</span>
                                @endif
                                @if($account->is_invoice_pool)
                                    <span class="px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">Invoice</span>
                                @endif
                                @if($account->is_membership_pool)
                                    <span class="px-2 py-1 text-xs font-medium bg-orange-100 text-orange-800 rounded-full">Membership</span>
                                @endif
                                @if($account->is_tickets_pool ?? false)
                                    <span class="px-2 py-1 text-xs font-medium bg-teal-100 text-teal-800 rounded-full">Tickets</span>
                                @endif
                                @if(!$account->is_pool && !$account->is_invoice_pool && !($account->is_membership_pool ?? false) && !($account->is_tickets_pool ?? false))
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Business</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <input type="checkbox" class="account-flag-checkbox rounded border-gray-300" data-flag="is_pool" {{ $account->is_pool ? 'checked' : '' }} title="Use for regular transactions">
                        </td>
                        <td class="px-6 py-4 text-center">
                            <input type="checkbox" class="account-flag-checkbox rounded border-gray-300" data-flag="is_invoice_pool" {{ $account->is_invoice_pool ? 'checked' : '' }} title="Use for invoices">
                        </td>
                        <td class="px-6 py-4 text-center">
                            <input type="checkbox" class="account-flag-checkbox rounded border-gray-300" data-flag="is_membership_pool" {{ $account->is_membership_pool ? 'checked' : '' }} title="Use for membership">
                        </td>
                        <td class="px-6 py-4 text-center">
                            <input type="checkbox" class="account-flag-checkbox rounded border-gray-300" data-flag="is_tickets_pool" {{ $account->is_tickets_pool ?? false ? 'checked' : '' }} title="Use for tickets">
                        </td>
                        <td class="px-6 py-4 text-center">
                            <input type="checkbox" class="account-flag-checkbox rounded border-gray-300" data-flag="is_active" {{ $account->is_active ? 'checked' : '' }} title="Active">
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $account->business ? $account->business->name : '-' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $account->usage_count }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            @if(($account->payments_received_count ?? 0) > 0)
                                <div class="flex flex-col">
                                    <span class="font-medium text-gray-900">{{ number_format($account->payments_received_count) }} payments</span>
                                    <span class="text-xs text-gray-600">₦{{ number_format($account->payments_received_amount ?? 0, 2) }}</span>
                                </div>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <a href="{{ route('admin.account-numbers.edit', $account) }}" class="text-primary hover:underline mr-3">Edit</a>
                            <form action="{{ route('admin.account-numbers.destroy', $account) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="13" class="px-6 py-4 text-center text-sm text-gray-500">No account numbers found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($accountNumbers->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $accountNumbers->links() }}
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.account-flag-checkbox').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
        var self = this;
        var row = this.closest('tr');
        var url = row.dataset.updateUrl;
        var formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('_method', 'PATCH');
        formData.append('is_pool', row.querySelector('[data-flag="is_pool"]').checked ? '1' : '0');
        formData.append('is_invoice_pool', row.querySelector('[data-flag="is_invoice_pool"]').checked ? '1' : '0');
        formData.append('is_membership_pool', row.querySelector('[data-flag="is_membership_pool"]').checked ? '1' : '0');
        formData.append('is_tickets_pool', row.querySelector('[data-flag="is_tickets_pool"]').checked ? '1' : '0');
        formData.append('is_active', row.querySelector('[data-flag="is_active"]').checked ? '1' : '0');
        fetch(url, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function(r) {
            if (!r.ok) throw new Error('Update failed');
            return r.json();
        }).then(function() {
            window.location.reload();
        }).catch(function() {
            alert('Failed to update. Please try again.');
            self.checked = !self.checked;
        });
    });
});
</script>
@endpush
@endsection

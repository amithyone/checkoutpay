@extends('layouts.admin')

@section('title', 'Email Accounts')
@section('page-title', 'Email Accounts')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Manage Email Accounts</h3>
            <p class="text-sm text-gray-600 mt-1">Configure email accounts for monitoring bank transfer notifications</p>
        </div>
        <a href="{{ route('admin.email-accounts.create') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 flex items-center">
            <i class="fas fa-plus mr-2"></i> Add Email Account
        </a>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Host</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Port</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Businesses</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($emailAccounts as $emailAccount)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $emailAccount->name }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $emailAccount->email }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $emailAccount->host }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $emailAccount->port }}</td>
                        <td class="px-6 py-4">
                            @if($emailAccount->is_active)
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Active</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $emailAccount->businesses()->count() }} business(es)
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="flex items-center space-x-2">
                                <button onclick="testConnection({{ $emailAccount->id }})" 
                                    class="text-blue-600 hover:text-blue-900" title="Test Connection">
                                    <i class="fas fa-plug"></i>
                                </button>
                                <a href="{{ route('admin.email-accounts.edit', $emailAccount) }}" 
                                    class="text-primary hover:text-primary/80" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.email-accounts.destroy', $emailAccount) }}" 
                                    method="POST" class="inline" 
                                    onsubmit="return confirm('Are you sure? This will remove email account assignment from businesses.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                            No email accounts found. <a href="{{ route('admin.email-accounts.create') }}" class="text-primary hover:underline">Create one</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($emailAccounts->hasPages())
    <div class="flex justify-center">
        {{ $emailAccounts->links() }}
    </div>
    @endif
</div>

<script>
function testConnection(id) {
    fetch(`/admin/email-accounts/${id}/test-connection`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Connection successful!');
        } else {
            alert('❌ Connection failed: ' + data.message);
        }
    })
    .catch(error => {
        alert('❌ Error testing connection: ' + error.message);
    });
}
</script>
@endsection

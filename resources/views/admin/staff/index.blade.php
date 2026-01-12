@extends('layouts.admin')

@section('title', 'Staff Management')
@section('page-title', 'Staff Management')

@section('content')
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Staff & Admin Accounts</h3>
            <p class="text-sm text-gray-500 mt-1">Manage staff members and their permissions</p>
        </div>
        <a href="{{ route('admin.staff.create') }}" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
            <i class="fas fa-plus mr-2"></i> Add Staff
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('admin.staff.index') }}" class="mb-6 flex flex-wrap gap-4">
        <div>
            <select name="role" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Roles</option>
                <option value="staff" {{ request('role') === 'staff' ? 'selected' : '' }}>Staff</option>
                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                <option value="support" {{ request('role') === 'support' ? 'selected' : '' }}>Support</option>
            </select>
        </div>
        <div>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Status</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <div class="flex-1">
            <input type="text" name="search" placeholder="Search by name or email..." 
                value="{{ request('search') }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
            <i class="fas fa-search mr-2"></i> Filter
        </button>
        <a href="{{ route('admin.staff.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
            Clear
        </a>
    </form>

    <!-- Staff Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($staff as $member)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-semibold">
                                {{ substr($member->name, 0, 1) }}
                            </div>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">{{ $member->name }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">{{ $member->email }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                            @if($member->role === 'super_admin') bg-purple-100 text-purple-800
                            @elseif($member->role === 'admin') bg-blue-100 text-blue-800
                            @elseif($member->role === 'staff') bg-green-100 text-green-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ str_replace('_', ' ', ucfirst($member->role)) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($member->is_active)
                            <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                        @else
                            <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Inactive</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $member->created_at->format('M d, Y') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex items-center justify-end space-x-2">
                            @if(!$member->isSuperAdmin())
                            <a href="{{ route('admin.staff.edit', $member) }}" class="text-primary hover:text-primary/80">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('admin.staff.toggle-status', $member) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-yellow-600 hover:text-yellow-800"
                                    onclick="return confirm('Are you sure you want to {{ $member->is_active ? 'deactivate' : 'activate' }} this account?')">
                                    <i class="fas fa-{{ $member->is_active ? 'ban' : 'check' }}"></i>
                                </button>
                            </form>
                            @if($member->id !== auth('admin')->id())
                            <form action="{{ route('admin.staff.destroy', $member) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800"
                                    onclick="return confirm('Are you sure you want to delete this staff member?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            @endif
                            @else
                            <span class="text-gray-400 text-xs">Super Admin</span>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                        No staff members found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $staff->links() }}
    </div>
</div>
@endsection

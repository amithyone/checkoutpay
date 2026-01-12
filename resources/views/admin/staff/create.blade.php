@extends('layouts.admin')

@section('title', 'Add Staff Member')
@section('page-title', 'Add Staff Member')

@section('content')
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <form action="{{ route('admin.staff.store') }}" method="POST">
        @csrf
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Password <span class="text-red-500">*</span></label>
                <input type="password" name="password" required minlength="8"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                <p class="mt-1 text-xs text-gray-500">Minimum 8 characters</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password <span class="text-red-500">*</span></label>
                <input type="password" name="password_confirmation" required minlength="8"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Role <span class="text-red-500">*</span></label>
                <select name="role" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    <option value="">Select Role</option>
                    <option value="staff" {{ old('role') === 'staff' ? 'selected' : '' }}>Staff - Review transactions, manage tickets, test transactions</option>
                    <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin - Full access except balance updates</option>
                    <option value="support" {{ old('role') === 'support' ? 'selected' : '' }}>Support - Manage support tickets only</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">
                    <strong>Staff:</strong> Can review transactions, manage businesses, handle tickets, test transactions. Cannot update balances or manage settings.<br>
                    <strong>Admin:</strong> Full access except updating business balances (super admin only).<br>
                    <strong>Support:</strong> Can only manage support tickets.
                </p>
            </div>

            <div>
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-primary focus:ring-primary">
                    <span class="text-sm text-gray-700">Active Account</span>
                </label>
            </div>

            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                <a href="{{ route('admin.staff.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    Create Staff Member
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@extends('layouts.admin')

@section('title', 'Profile Settings')
@section('page-title', 'Profile Settings')

@section('content')
<div class="space-y-6">
    <!-- Profile Information -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">
            <i class="fas fa-user mr-2 text-primary"></i>Profile Information
        </h3>

        <form action="{{ route('admin.profile.update') }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Name -->
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                    Full Name <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    value="{{ old('name', $admin->name) }}"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('name') border-red-500 @enderror"
                >
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                    Email Address <span class="text-red-500">*</span>
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="{{ old('email', $admin->email) }}"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('email') border-red-500 @enderror"
                >
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Role (Read-only) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Role
                </label>
                <div class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50">
                    <span class="text-gray-700 capitalize">{{ str_replace('_', ' ', $admin->role) }}</span>
                </div>
                <p class="mt-1 text-xs text-gray-500">Role cannot be changed</p>
            </div>

            <!-- Status (Read-only) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Status
                </label>
                <div class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50">
                    <span class="inline-flex items-center">
                        @if($admin->is_active)
                            <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                            <span class="text-gray-700">Active</span>
                        @else
                            <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                            <span class="text-gray-700">Inactive</span>
                        @endif
                    </span>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium transition-colors">
                    <i class="fas fa-save mr-2"></i>Update Profile
                </button>
            </div>
        </form>
    </div>

    <!-- Change Password -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">
            <i class="fas fa-lock mr-2 text-primary"></i>Change Password
        </h3>

        <form action="{{ route('admin.profile.update-password') }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Current Password -->
            <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">
                    Current Password <span class="text-red-500">*</span>
                </label>
                <input 
                    type="password" 
                    id="current_password" 
                    name="current_password" 
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('current_password') border-red-500 @enderror"
                >
                @error('current_password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- New Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                    New Password <span class="text-red-500">*</span>
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('password') border-red-500 @enderror"
                >
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">Password must be at least 8 characters long</p>
            </div>

            <!-- Confirm Password -->
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                    Confirm New Password <span class="text-red-500">*</span>
                </label>
                <input 
                    type="password" 
                    id="password_confirmation" 
                    name="password_confirmation" 
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                >
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium transition-colors">
                    <i class="fas fa-key mr-2"></i>Update Password
                </button>
            </div>
        </form>
    </div>

    <!-- Account Information -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">
            <i class="fas fa-info-circle mr-2 text-primary"></i>Account Information
        </h3>

        <div class="space-y-4">
            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                <span class="text-sm font-medium text-gray-700">Account Created</span>
                <span class="text-sm text-gray-600">{{ $admin->created_at->format('M d, Y') }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                <span class="text-sm font-medium text-gray-700">Last Updated</span>
                <span class="text-sm text-gray-600">{{ $admin->updated_at->format('M d, Y') }}</span>
            </div>
            @if($admin->deleted_at)
            <div class="flex justify-between items-center py-2">
                <span class="text-sm font-medium text-gray-700">Deleted At</span>
                <span class="text-sm text-red-600">{{ $admin->deleted_at->format('M d, Y') }}</span>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

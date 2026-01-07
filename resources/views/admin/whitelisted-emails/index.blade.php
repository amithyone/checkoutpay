@extends('layouts.admin')

@section('title', 'Whitelisted Email Addresses')
@section('page-title', 'Whitelisted Email Addresses')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Manage Whitelisted Email Addresses</h3>
            <p class="text-sm text-gray-600 mt-1">Only emails from whitelisted addresses will be accepted via Zapier webhook</p>
        </div>
        <a href="{{ route('admin.whitelisted-emails.create') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 flex items-center">
            <i class="fas fa-plus mr-2"></i> Add Whitelisted Email
        </a>
    </div>

    <!-- Info Alert -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-600"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">How Whitelisting Works</h3>
                <div class="mt-2 text-sm text-blue-700">
                    <ul class="list-disc list-inside space-y-1">
                        <li>Add specific email addresses (e.g., <code class="bg-blue-100 px-1 rounded">alerts@gtbank.com</code>)</li>
                        <li>Add domains (e.g., <code class="bg-blue-100 px-1 rounded">@gtbank.com</code>) to whitelist all emails from that domain</li>
                        <li>Only emails from whitelisted addresses will be processed</li>
                        <li>Emails from non-whitelisted addresses will be rejected with a 403 error</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email/Domain</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($whitelistedEmails as $whitelistedEmail)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">
                                <code class="bg-gray-100 px-2 py-1 rounded">{{ $whitelistedEmail->email }}</code>
                            </div>
                            @if(str_starts_with($whitelistedEmail->email, '@'))
                                <span class="text-xs text-gray-500 mt-1 block">Domain whitelist</span>
                            @else
                                <span class="text-xs text-gray-500 mt-1 block">Specific email</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $whitelistedEmail->description ?? '-' }}
                        </td>
                        <td class="px-6 py-4">
                            @if($whitelistedEmail->is_active)
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Active</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $whitelistedEmail->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.whitelisted-emails.edit', $whitelistedEmail) }}" class="text-sm text-primary hover:underline">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </a>
                                <form action="{{ route('admin.whitelisted-emails.destroy', $whitelistedEmail) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove this whitelisted email?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-600 hover:underline">
                                        <i class="fas fa-trash mr-1"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                            <div class="py-8">
                                <i class="fas fa-shield-alt text-gray-400 text-4xl mb-3"></i>
                                <p class="text-gray-600">No whitelisted email addresses yet</p>
                                <p class="text-sm text-gray-500 mt-2">Add your first whitelisted email address to get started</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($whitelistedEmails->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $whitelistedEmails->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

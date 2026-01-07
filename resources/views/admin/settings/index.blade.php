@extends('layouts.admin')

@section('title', 'Settings')
@section('page-title', 'Settings')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    <!-- Payment Settings -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-money-bill-wave mr-2 text-primary"></i>Payment Settings
        </h3>

        <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="payment_time_window_minutes" class="block text-sm font-medium text-gray-700 mb-2">
                    Payment Time Window (Minutes)
                </label>
                <input 
                    type="number" 
                    id="payment_time_window_minutes" 
                    name="payment_time_window_minutes" 
                    value="{{ $settings['payment']['payment_time_window_minutes'] ?? 15 }}"
                    min="1" 
                    max="1440"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    required
                >
                <p class="mt-2 text-sm text-gray-500">
                    Maximum time window (in minutes) for matching emails with payment requests. 
                    Emails received after this time will not be matched. 
                    <span class="font-medium">Default: 15 minutes</span>
                </p>
                <p class="mt-1 text-xs text-gray-400">
                    Range: 1 minute to 1440 minutes (24 hours)
                </p>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90 flex items-center">
                    <i class="fas fa-save mr-2"></i> Save Payment Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Zapier Integration Settings -->
    <div class="bg-gradient-to-r from-purple-50 to-indigo-50 rounded-lg shadow-sm border-2 border-purple-200 p-6">
        <div class="flex items-center mb-4">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-bolt text-purple-600 text-xl"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Zapier Integration</h3>
                <p class="text-sm text-gray-600">Configure Zapier webhook security and email whitelisting</p>
            </div>
        </div>

        <!-- Zapier Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg p-4 border border-purple-100">
                <p class="text-xs text-gray-500 mb-1">Total Logs</p>
                <p class="text-2xl font-bold text-purple-600">{{ number_format($zapierStats['total']) }}</p>
            </div>
            <div class="bg-white rounded-lg p-4 border border-purple-100">
                <p class="text-xs text-gray-500 mb-1">Today</p>
                <p class="text-2xl font-bold text-indigo-600">{{ number_format($zapierStats['today']) }}</p>
            </div>
            <div class="bg-white rounded-lg p-4 border border-purple-100">
                <p class="text-xs text-gray-500 mb-1">Matched</p>
                <p class="text-2xl font-bold text-green-600">{{ number_format($zapierStats['matched']) }}</p>
            </div>
            <div class="bg-white rounded-lg p-4 border border-purple-100">
                <p class="text-xs text-gray-500 mb-1">Errors</p>
                <p class="text-2xl font-bold text-red-600">{{ number_format($zapierStats['error']) }}</p>
            </div>
        </div>

        @if($zapierStats['last_log'])
            <div class="mb-6 p-3 bg-white rounded-lg border border-purple-100">
                <p class="text-xs text-gray-500">Last Zapier Request</p>
                <p class="text-sm font-medium text-gray-900">{{ $zapierStats['last_log']->created_at->diffForHumans() }}</p>
                <p class="text-xs text-gray-500 mt-1">Status: 
                    @if($zapierStats['last_log']->status === 'matched')
                        <span class="text-green-600 font-medium">Matched</span>
                    @elseif($zapierStats['last_log']->status === 'error')
                        <span class="text-red-600 font-medium">Error</span>
                    @else
                        <span class="text-blue-600 font-medium">{{ ucfirst($zapierStats['last_log']->status) }}</span>
                    @endif
                </p>
            </div>
        @endif

        <div class="flex justify-end mb-4">
            <a href="{{ route('admin.zapier-logs.index') }}" class="text-sm text-purple-600 hover:text-purple-800">
                <i class="fas fa-list mr-1"></i> View All Zapier Logs
            </a>
        </div>

        <!-- Security Settings -->
        <div class="bg-white rounded-lg shadow-sm border border-purple-200 p-6 mb-6">
            <h4 class="text-md font-semibold text-gray-900 mb-4">
                <i class="fas fa-shield-alt mr-2 text-purple-600"></i>Webhook Security
            </h4>

            <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="zapier_webhook_secret" class="block text-sm font-medium text-gray-700 mb-2">
                        Zapier Webhook Secret
                    </label>
                    <input 
                        type="text" 
                        id="zapier_webhook_secret" 
                        name="zapier_webhook_secret" 
                        value="{{ $settings['security']['zapier_webhook_secret'] ?? '' }}"
                        placeholder="Enter a secret key (min 16 characters)"
                        minlength="16"
                        maxlength="255"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    >
                    <p class="mt-2 text-sm text-gray-500">
                        <strong>Security Feature:</strong> Add a secret key to authenticate Zapier webhook requests.
                    </p>
                    <p class="mt-1 text-xs text-gray-400">
                        In Zapier, add this secret as a header: <code class="bg-gray-100 px-1 rounded">X-Zapier-Secret</code>
                    </p>
                    <p class="mt-1 text-xs text-gray-400">
                        <strong>Note:</strong> Leave empty to disable webhook authentication (not recommended for production).
                    </p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 flex items-center">
                        <i class="fas fa-save mr-2"></i> Save Webhook Secret
                    </button>
                </div>
            </form>
        </div>

        <!-- Whitelisted Emails -->
        <div class="bg-white rounded-lg shadow-sm border border-purple-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-md font-semibold text-gray-900">
                    <i class="fas fa-envelope-check mr-2 text-purple-600"></i>Whitelisted Email Addresses
                </h4>
                <span class="text-sm text-gray-600">{{ $whitelistedEmails->count() }} whitelisted</span>
            </div>

            <p class="text-sm text-gray-600 mb-4">
                Only emails from whitelisted addresses will be accepted via Zapier webhook. 
                All other requests are rejected with a 403 error.
            </p>

            <!-- Add Whitelisted Email Form -->
            <form action="{{ route('admin.settings.add-whitelisted-email') }}" method="POST" class="mb-6 p-4 bg-gray-50 rounded-lg">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="whitelist_email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email Address or Domain <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="whitelist_email" 
                            name="email" 
                            placeholder="alerts@gtbank.com or @gtbank.com"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('email') border-red-500 @enderror"
                            required
                        >
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="whitelist_description" class="block text-sm font-medium text-gray-700 mb-2">
                            Description (Optional)
                        </label>
                        <input 
                            type="text" 
                            id="whitelist_description" 
                            name="description" 
                            placeholder="e.g., GTBank transaction notifications"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        >
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 flex items-center justify-center">
                            <i class="fas fa-plus mr-2"></i> Add to Whitelist
                        </button>
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    <strong>Examples:</strong> 
                    <code class="bg-gray-100 px-1 rounded">alerts@gtbank.com</code> (specific email), 
                    <code class="bg-gray-100 px-1 rounded">@gtbank.com</code> (all emails from domain)
                </p>
            </form>

            <!-- Whitelisted Emails List -->
            @if($whitelistedEmails->count() > 0)
                <div class="space-y-2">
                    @foreach($whitelistedEmails as $whitelistedEmail)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <code class="bg-white px-2 py-1 rounded text-sm font-medium">{{ $whitelistedEmail->email }}</code>
                                    @if($whitelistedEmail->is_active)
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Active</span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                    @endif
                                </div>
                                @if($whitelistedEmail->description)
                                    <p class="text-xs text-gray-500 mt-1">{{ $whitelistedEmail->description }}</p>
                                @endif
                            </div>
                            <form action="{{ route('admin.settings.remove-whitelisted-email', $whitelistedEmail) }}" method="POST" class="ml-4" onsubmit="return confirm('Are you sure you want to remove this whitelisted email?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 bg-gray-50 rounded-lg">
                    <i class="fas fa-shield-alt text-gray-400 text-4xl mb-3"></i>
                    <p class="text-gray-600">No whitelisted emails yet</p>
                    <p class="text-sm text-gray-500 mt-2">Add your first whitelisted email address above</p>
                </div>
            @endif

            <!-- Info Box -->
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <h5 class="text-sm font-medium text-blue-800 mb-2">How Whitelisting Works</h5>
                <ul class="text-sm text-blue-700 list-disc list-inside space-y-1">
                    <li>Add specific email addresses (e.g., <code class="bg-blue-100 px-1 rounded">alerts@gtbank.com</code>)</li>
                    <li>Add domains (e.g., <code class="bg-blue-100 px-1 rounded">@gtbank.com</code>) to whitelist all emails from that domain</li>
                    <li>Only emails from whitelisted addresses will be processed</li>
                    <li>Emails from non-whitelisted addresses will be rejected with a 403 error</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Setup Instructions -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-info-circle mr-2 text-primary"></i>Quick Setup Guide
        </h3>
        <div class="space-y-4">
            <div class="flex items-start">
                <span class="flex-shrink-0 w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center font-semibold mr-3">1</span>
                <div>
                    <h4 class="font-medium text-gray-900">Add Your Bank Emails to Whitelist</h4>
                    <p class="text-sm text-gray-600 mt-1">Add email addresses like <code class="bg-gray-100 px-1 rounded">alerts@gtbank.com</code> or domains like <code class="bg-gray-100 px-1 rounded">@gtbank.com</code> in the Whitelisted Email Addresses section above.</p>
                </div>
            </div>
            <div class="flex items-start">
                <span class="flex-shrink-0 w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center font-semibold mr-3">2</span>
                <div>
                    <h4 class="font-medium text-gray-900">Set Webhook Secret</h4>
                    <p class="text-sm text-gray-600 mt-1">Generate a strong secret key (minimum 16 characters) in the Webhook Security section above. Then add it as a header <code class="bg-gray-100 px-1 rounded">X-Zapier-Secret</code> in your Zapier webhook action.</p>
                </div>
            </div>
            <div class="flex items-start">
                <span class="flex-shrink-0 w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center font-semibold mr-3">3</span>
                <div>
                    <h4 class="font-medium text-gray-900">Test Your Setup</h4>
                    <p class="text-sm text-gray-600 mt-1">Send a test email from a whitelisted address. Check the Zapier Logs to verify it was received and processed correctly.</p>
                </div>
            </div>
        </div>
        <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg">
            <h5 class="text-sm font-medium text-green-800 mb-2">✅ Security Status</h5>
            <p class="text-sm text-green-700">
                The system now only accepts:
            </p>
            <ul class="text-sm text-green-700 list-disc list-inside mt-2 space-y-1">
                <li>Requests from Zapier with the correct secret</li>
                <li>Emails from whitelisted addresses</li>
                <li>All other requests are rejected</li>
            </ul>
        </div>
    </div>

    <!-- General Settings -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-cog mr-2 text-primary"></i>General Settings
        </h3>
        <p class="text-sm text-gray-600">More settings coming soon...</p>
    </div>

    <!-- Email Settings -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-envelope mr-2 text-primary"></i>Email Settings
        </h3>
        <p class="text-sm text-gray-600">More settings coming soon...</p>
    </div>
</div>
@endsection

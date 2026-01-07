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
                    <i class="fas fa-save mr-2"></i> Save Settings
                </button>
            </div>
        </form>
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

    <!-- Security Settings -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-shield-alt mr-2 text-primary"></i>Security Settings
        </h3>

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
                <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90 flex items-center">
                    <i class="fas fa-save mr-2"></i> Save Security Settings
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

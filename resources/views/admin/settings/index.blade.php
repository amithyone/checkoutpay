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
                    Email Matching Time Window (Minutes)
                </label>
                <input 
                    type="number" 
                    id="payment_time_window_minutes" 
                    name="payment_time_window_minutes" 
                    value="{{ $settings['payment']['payment_time_window_minutes'] ?? 120 }}"
                    min="1" 
                    max="1440"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    required
                >
                <p class="mt-2 text-sm text-gray-500">
                    Maximum time window (in minutes) for matching emails with payment requests. 
                    Emails received after this time will not be matched. 
                    <span class="font-medium">Default: 120 minutes (2 hours)</span>
                </p>
                <p class="mt-1 text-xs text-gray-400">
                    Range: 1 minute to 1440 minutes (24 hours)
                </p>
            </div>

            <div>
                <label for="transaction_pending_time_minutes" class="block text-sm font-medium text-gray-700 mb-2">
                    Transaction Pending Time (Minutes) <span class="text-red-500">*</span>
                </label>
                <input 
                    type="number" 
                    id="transaction_pending_time_minutes" 
                    name="transaction_pending_time_minutes" 
                    value="{{ $settings['payment']['transaction_pending_time_minutes'] ?? 1440 }}"
                    min="5" 
                    max="10080"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    required
                >
                <p class="mt-2 text-sm text-gray-500">
                    Time (in minutes) before a pending transaction expires. After expiration, transaction will be automatically marked as expired and <strong>cannot be matched</strong>. 
                    <span class="font-medium">Default: 1440 minutes (24 hours)</span>
                </p>
                <p class="mt-1 text-xs text-gray-400">
                    Range: 5 minutes to 10080 minutes (7 days). 
                    <strong>Note:</strong> Once expired, transactions cannot be matched even if payment email arrives later.
                </p>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90 flex items-center">
                    <i class="fas fa-save mr-2"></i> Save Payment Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Email Settings -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-envelope mr-2 text-primary"></i>Email Settings
        </h3>

        <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            
            <!-- Hidden fields to preserve payment settings when updating email settings -->
            <input type="hidden" name="payment_time_window_minutes" value="{{ $settings['payment']['payment_time_window_minutes'] ?? 120 }}">
            <input type="hidden" name="transaction_pending_time_minutes" value="{{ $settings['payment']['transaction_pending_time_minutes'] ?? 1440 }}">

            <div class="flex items-center space-x-3">
                <input 
                    type="checkbox" 
                    id="disable_imap_fetching" 
                    name="disable_imap_fetching" 
                    value="1"
                    {{ ($settings['email']['disable_imap_fetching'] ?? false) ? 'checked' : '' }}
                    class="w-5 h-5 text-primary border-gray-300 rounded focus:ring-primary focus:ring-2"
                >
                <div>
                    <label for="disable_imap_fetching" class="block text-sm font-medium text-gray-700 cursor-pointer">
                        Disable IMAP Email Fetching
                    </label>
                    <p class="mt-1 text-sm text-gray-500">
                        When enabled, the system will <strong>only use direct filesystem reading</strong> to read emails from the server's mail files. 
                        IMAP fetching will be completely disabled. 
                        <span class="font-medium text-green-600">Recommended for shared hosting</span> where direct filesystem access is more reliable.
                    </p>
                    <p class="mt-2 text-xs text-gray-400">
                        <strong>Note:</strong> When IMAP is disabled, only the <code>payment:read-emails-direct</code> command will run. 
                        This method reads emails directly from the server's mail directories (Maildir/mbox format) and is more reliable for shared hosting.
                    </p>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90 flex items-center">
                    <i class="fas fa-save mr-2"></i> Save Email Settings
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
</div>
@endsection

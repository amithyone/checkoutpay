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

    <!-- Withdrawal Notifications (Admin) -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-bell mr-2 text-primary"></i>Withdrawal Notifications (Admin)
        </h3>
        <p class="text-sm text-gray-600 mb-4">Get notified on every new withdrawal request so you can process them ASAP. Configure email and/or Telegram.</p>

        <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="admin_withdrawal_notification_email" class="block text-sm font-medium text-gray-700 mb-2">Admin email for withdrawal alerts</label>
                <input type="email" id="admin_withdrawal_notification_email" name="admin_withdrawal_notification_email"
                    value="{{ old('admin_withdrawal_notification_email', \App\Models\Setting::get('admin_withdrawal_notification_email')) }}"
                    placeholder="admin@example.com"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                <p class="mt-1 text-xs text-gray-500">One email per new withdrawal request.</p>
            </div>

            <div>
                <label for="admin_telegram_bot_token" class="block text-sm font-medium text-gray-700 mb-2">Telegram bot token (for admin alerts)</label>
                <input type="text" id="admin_telegram_bot_token" name="admin_telegram_bot_token"
                    value="{{ old('admin_telegram_bot_token', \App\Models\Setting::get('admin_telegram_bot_token')) }}"
                    placeholder="123456:ABC-DEF..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>

            <div>
                <label for="admin_telegram_chat_id" class="block text-sm font-medium text-gray-700 mb-2">Telegram chat ID (admin)</label>
                <input type="text" id="admin_telegram_chat_id" name="admin_telegram_chat_id"
                    value="{{ old('admin_telegram_chat_id', \App\Models\Setting::get('admin_telegram_chat_id')) }}"
                    placeholder="-1001234567890"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                <p class="mt-1 text-xs text-gray-500">Create a bot with @BotFather, add it to a group or use your chat ID. One Telegram message per new withdrawal request.</p>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90 flex items-center">
                    <i class="fas fa-save mr-2"></i> Save Withdrawal Notifications
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

        <form action="{{ route('admin.settings.update-general') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- Site Name -->
            <div>
                <label for="site_name" class="block text-sm font-medium text-gray-700 mb-2">
                    Site Name
                </label>
                <input 
                    type="text" 
                    id="site_name" 
                    name="site_name" 
                    value="{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                >
            </div>

            <!-- Site Logo Upload (Landing Pages) -->
            <div>
                <label for="logo" class="block text-sm font-medium text-gray-700 mb-2">
                    Landing Pages Logo
                    <span class="text-xs text-gray-500 font-normal">(Home, Pricing pages)</span>
                </label>
                @php
                    $logo = \App\Models\Setting::get('site_logo');
                    $logoPath = $logo ? storage_path('app/public/' . $logo) : null;
                    $logoExists = $logo && $logoPath && file_exists($logoPath);
                @endphp
                @if($logoExists)
                    <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <img src="{{ asset('storage/' . $logo) }}?v={{ time() }}" alt="Current Logo" class="h-16 object-contain max-w-full" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <p class="text-xs text-red-500 mt-1" style="display: none;">Failed to load logo image</p>
                        <p class="text-xs text-gray-500 mt-1">Current landing pages logo</p>
                    </div>
                @elseif($logo)
                    <div class="mb-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                        <p class="text-xs text-yellow-700">Logo file not found at: {{ $logo }}</p>
                    </div>
                @endif
                <input 
                    type="file" 
                    id="logo" 
                    name="logo" 
                    accept="image/png,image/jpeg,image/jpg,image/svg+xml"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                >
                <p class="mt-2 text-xs text-gray-500">Recommended: PNG, JPG, or SVG. Max size: 2MB</p>
            </div>

            <!-- Admin Logo Upload -->
            <div>
                <label for="admin_logo" class="block text-sm font-medium text-gray-700 mb-2">
                    Admin Panel Logo
                    <span class="text-xs text-gray-500 font-normal">(Admin dashboard sidebar)</span>
                </label>
                @php
                    $adminLogo = \App\Models\Setting::get('admin_logo');
                    $adminLogoPath = $adminLogo ? storage_path('app/public/' . $adminLogo) : null;
                    $adminLogoExists = $adminLogo && $adminLogoPath && file_exists($adminLogoPath);
                @endphp
                @if($adminLogoExists)
                    <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <img src="{{ asset('storage/' . $adminLogo) }}?v={{ time() }}" alt="Current Admin Logo" class="h-16 object-contain max-w-full" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <p class="text-xs text-red-500 mt-1" style="display: none;">Failed to load admin logo image</p>
                        <p class="text-xs text-gray-500 mt-1">Current admin panel logo</p>
                    </div>
                @elseif($adminLogo)
                    <div class="mb-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                        <p class="text-xs text-yellow-700">Admin logo file not found at: {{ $adminLogo }}</p>
                    </div>
                @else
                    <p class="text-xs text-gray-500 mb-2">If not set, will use landing pages logo as fallback</p>
                @endif
                <input 
                    type="file" 
                    id="admin_logo" 
                    name="admin_logo" 
                    accept="image/png,image/jpeg,image/jpg,image/svg+xml"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                >
                <p class="mt-2 text-xs text-gray-500">Recommended: PNG, JPG, or SVG. Max size: 2MB</p>
            </div>

            <!-- Business Logo Upload -->
            <div>
                <label for="business_logo" class="block text-sm font-medium text-gray-700 mb-2">
                    Business Dashboard Logo
                    <span class="text-xs text-gray-500 font-normal">(Business dashboard sidebar)</span>
                </label>
                @php
                    $businessLogo = \App\Models\Setting::get('business_logo');
                    $businessLogoPath = $businessLogo ? storage_path('app/public/' . $businessLogo) : null;
                    $businessLogoExists = $businessLogo && $businessLogoPath && file_exists($businessLogoPath);
                @endphp
                @if($businessLogoExists)
                    <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <img src="{{ asset('storage/' . $businessLogo) }}?v={{ time() }}" alt="Current Business Logo" class="h-16 object-contain max-w-full" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <p class="text-xs text-red-500 mt-1" style="display: none;">Failed to load business logo image</p>
                        <p class="text-xs text-gray-500 mt-1">Current business dashboard logo</p>
                    </div>
                @elseif($businessLogo)
                    <div class="mb-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                        <p class="text-xs text-yellow-700">Business logo file not found at: {{ $businessLogo }}</p>
                    </div>
                @else
                    <p class="text-xs text-gray-500 mb-2">If not set, will use landing pages logo as fallback</p>
                @endif
                <input 
                    type="file" 
                    id="business_logo" 
                    name="business_logo" 
                    accept="image/png,image/jpeg,image/jpg,image/svg+xml"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                >
                <p class="mt-2 text-xs text-gray-500">Recommended: PNG, JPG, or SVG. Max size: 2MB</p>
            </div>

            <!-- Favicon Upload -->
            <div>
                <label for="favicon" class="block text-sm font-medium text-gray-700 mb-2">
                    Favicon
                </label>
                @php
                    $favicon = \App\Models\Setting::get('site_favicon');
                    $faviconPath = $favicon ? storage_path('app/public/' . $favicon) : null;
                    $faviconExists = $favicon && $faviconPath && file_exists($faviconPath);
                @endphp
                @if($faviconExists)
                    <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <img src="{{ asset('storage/' . $favicon) }}?v={{ time() }}" alt="Current Favicon" class="h-8 w-8 object-contain" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <p class="text-xs text-red-500 mt-1" style="display: none;">Failed to load favicon image</p>
                        <p class="text-xs text-gray-500 mt-1">Current favicon</p>
                    </div>
                @elseif($favicon)
                    <div class="mb-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                        <p class="text-xs text-yellow-700">Favicon file not found at: {{ $favicon }}</p>
                    </div>
                @endif
                <input 
                    type="file" 
                    id="favicon" 
                    name="favicon" 
                    accept="image/png,image/x-icon"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                >
                <p class="mt-2 text-xs text-gray-500">Recommended: PNG or ICO. Max size: 512KB</p>
            </div>

            <!-- Email Logo Upload (Black Logo for Email Templates) -->
            <div>
                <label for="email_logo" class="block text-sm font-medium text-gray-700 mb-2">
                    Email Logo (Black Logo)
                    <span class="text-xs text-gray-500 font-normal">(Used in email templates)</span>
                </label>
                @php
                    $emailLogo = \App\Models\Setting::get('email_logo');
                    $emailLogoPath = $emailLogo ? storage_path('app/public/' . $emailLogo) : null;
                    $emailLogoExists = $emailLogo && $emailLogoPath && file_exists($emailLogoPath);
                @endphp
                @if($emailLogoExists)
                    <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <img src="{{ asset('storage/' . $emailLogo) }}?v={{ time() }}" alt="Current Email Logo" class="h-16 object-contain bg-gray-100 p-2 rounded max-w-full" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <p class="text-xs text-red-500 mt-1" style="display: none;">Failed to load email logo image</p>
                        <p class="text-xs text-gray-500 mt-1">Current email logo</p>
                    </div>
                @elseif($emailLogo)
                    <div class="mb-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                        <p class="text-xs text-yellow-700">Email logo file not found at: {{ $emailLogo }}</p>
                    </div>
                @endif
                <input 
                    type="file" 
                    id="email_logo" 
                    name="email_logo" 
                    accept="image/png,image/jpeg,image/jpg,image/svg+xml"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                >
                <p class="mt-2 text-xs text-gray-500">Recommended: PNG, JPG, or SVG. Max size: 2MB. This logo will be used in all email templates.</p>
            </div>

            <!-- Rentals Page Accent Color -->
            <div class="border-t border-gray-200 pt-6">
                <h4 class="text-md font-semibold text-gray-900 mb-4">Rentals Page</h4>
                <p class="text-sm text-gray-600 mb-4">Color used for the search bar, filter button, active category pills, &quot;Add to cart&quot; button, and floating cart icon on the public rentals page.</p>
                <div class="flex flex-wrap items-center gap-4">
                    <div>
                        <label for="rentals_accent_color" class="block text-sm font-medium text-gray-700 mb-2">Accent color</label>
                        <div class="flex items-center gap-2">
                            <input type="color" id="rentals_accent_color" name="rentals_accent_color" value="{{ \App\Models\Setting::get('rentals_accent_color', '#000000') }}" class="h-10 w-14 rounded border border-gray-300 cursor-pointer">
                            <input type="text" id="rentals_accent_color_hex" value="{{ \App\Models\Setting::get('rentals_accent_color', '#000000') }}" maxlength="7" placeholder="#000000" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary font-mono text-sm w-24">
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Default: black (#000000).</p>
                    </div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var c = document.getElementById('rentals_accent_color');
                        var h = document.getElementById('rentals_accent_color_hex');
                        var form = c && c.closest('form');
                        if (c && h) {
                            c.addEventListener('input', function() { h.value = this.value; });
                            h.addEventListener('input', function() { if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) c.value = this.value; });
                            if (form) form.addEventListener('submit', function() {
                                if (h && /^#[0-9A-Fa-f]{6}$/.test(h.value)) c.value = h.value;
                            });
                        }
                    });
                </script>
            </div>

            <!-- Beta Badge Toggle -->
            <div class="border-t border-gray-200 pt-6">
                <h4 class="text-md font-semibold text-gray-900 mb-4">Display Settings</h4>
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <div class="flex-1">
                        <label for="show_beta_badge" class="block text-sm font-medium text-gray-700 mb-1">
                            Show Beta Badge
                        </label>
                        <p class="text-xs text-gray-500">
                            Display a floating "BETA" badge on all pages. 
                            @if(env('SHOW_BETA_BADGE') !== null)
                                <span class="text-orange-600 font-medium">Note: ENV variable SHOW_BETA_BADGE is set and takes priority.</span>
                            @else
                                You can also control this via <code class="text-xs bg-gray-200 px-1 rounded">SHOW_BETA_BADGE</code> in your .env file.
                            @endif
                        </p>
                    </div>
                    <div class="ml-4">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input 
                                type="checkbox" 
                                id="show_beta_badge" 
                                name="show_beta_badge" 
                                value="1"
                                {{ \App\Models\Setting::get('show_beta_badge', true) ? 'checked' : '' }}
                                {{ env('SHOW_BETA_BADGE') !== null ? 'disabled' : '' }}
                                class="sr-only peer"
                            >
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="border-t border-gray-200 pt-6">
                <h4 class="text-md font-semibold text-gray-900 mb-4">Contact Information</h4>

                <div class="space-y-4">
                    <div>
                        <label for="contact_email" class="block text-sm font-medium text-gray-700 mb-2">
                            Contact Email
                        </label>
                        <input 
                            type="email" 
                            id="contact_email" 
                            name="contact_email" 
                            value="{{ \App\Models\Setting::get('contact_email', '') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        >
                    </div>

                    <div>
                        <label for="contact_phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Contact Phone
                        </label>
                        <input 
                            type="text" 
                            id="contact_phone" 
                            name="contact_phone" 
                            value="{{ \App\Models\Setting::get('contact_phone', '') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        >
                    </div>

                    <div>
                        <label for="contact_address" class="block text-sm font-medium text-gray-700 mb-2">
                            Contact Address
                        </label>
                        <textarea 
                            id="contact_address" 
                            name="contact_address" 
                            rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        >{{ \App\Models\Setting::get('contact_address', '') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90 flex items-center">
                    <i class="fas fa-save mr-2"></i> Save General Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Charge Settings -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-percent mr-2 text-primary"></i>Default Charge Settings
        </h3>

        <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="default_charge_percentage" class="block text-sm font-medium text-gray-700 mb-2">
                        Default Charge Percentage (%)
                    </label>
                    <input 
                        type="number" 
                        id="default_charge_percentage" 
                        name="default_charge_percentage" 
                        value="{{ \App\Models\Setting::get('default_charge_percentage', 1) }}"
                        step="0.01"
                        min="0"
                        max="100"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        required
                    >
                    <p class="mt-2 text-sm text-gray-500">
                        Default percentage charge applied to all payments (e.g., 1 = 1%)
                    </p>
                </div>

                <div>
                    <label for="default_charge_fixed" class="block text-sm font-medium text-gray-700 mb-2">
                        Default Fixed Charge (₦)
                    </label>
                    <input 
                        type="number" 
                        id="default_charge_fixed" 
                        name="default_charge_fixed" 
                        value="{{ \App\Models\Setting::get('default_charge_fixed', 100) }}"
                        step="0.01"
                        min="0"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        required
                    >
                    <p class="mt-2 text-sm text-gray-500">
                        Default fixed charge amount added to all payments
                    </p>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90 flex items-center">
                    <i class="fas fa-save mr-2"></i> Save Charge Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Invoice Charge Settings -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-file-invoice-dollar mr-2 text-primary"></i>Invoice Charge Settings
        </h3>
        <p class="text-sm text-gray-600 mb-4">
            Configure charges for invoice payments. By default, invoices are free. You can set charges when invoice amount exceeds a particular threshold.
        </p>

        <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="invoice_charge_threshold" class="block text-sm font-medium text-gray-700 mb-2">
                        Charge Threshold Amount (₦)
                    </label>
                    <input 
                        type="number" 
                        id="invoice_charge_threshold" 
                        name="invoice_charge_threshold" 
                        value="{{ \App\Models\Setting::get('invoice_charge_threshold', 0) }}"
                        step="0.01"
                        min="0"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        placeholder="0 = Free (no charges)"
                    >
                    <p class="mt-2 text-sm text-gray-500">
                        Invoices below this amount are free. Charges apply when invoice amount exceeds this threshold.
                    </p>
                </div>

                <div>
                    <label for="invoice_charge_percentage" class="block text-sm font-medium text-gray-700 mb-2">
                        Invoice Charge Percentage (%)
                    </label>
                    <input 
                        type="number" 
                        id="invoice_charge_percentage" 
                        name="invoice_charge_percentage" 
                        value="{{ \App\Models\Setting::get('invoice_charge_percentage', 0) }}"
                        step="0.01"
                        min="0"
                        max="100"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    >
                    <p class="mt-2 text-sm text-gray-500">
                        Percentage charge applied to invoice payments above the threshold (e.g., 1.5 = 1.5%)
                    </p>
                </div>

                <div>
                    <label for="invoice_charge_fixed" class="block text-sm font-medium text-gray-700 mb-2">
                        Invoice Fixed Charge (₦)
                    </label>
                    <input 
                        type="number" 
                        id="invoice_charge_fixed" 
                        name="invoice_charge_fixed" 
                        value="{{ \App\Models\Setting::get('invoice_charge_fixed', 0) }}"
                        step="0.01"
                        min="0"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    >
                    <p class="mt-2 text-sm text-gray-500">
                        Fixed charge amount added to invoice payments above the threshold
                    </p>
                </div>

                <div>
                    <label for="invoice_charges_enabled" class="block text-sm font-medium text-gray-700 mb-2">
                        Enable Invoice Charges
                    </label>
                    <select 
                        id="invoice_charges_enabled" 
                        name="invoice_charges_enabled" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    >
                        <option value="0" {{ \App\Models\Setting::get('invoice_charges_enabled', false) ? '' : 'selected' }}>Disabled (Free)</option>
                        <option value="1" {{ \App\Models\Setting::get('invoice_charges_enabled', false) ? 'selected' : '' }}>Enabled</option>
                    </select>
                    <p class="mt-2 text-sm text-gray-500">
                        Enable or disable charges for invoice payments
                    </p>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-600 mt-0.5 mr-3"></i>
                    <div class="text-sm text-blue-800">
                        <p class="font-medium mb-1">How Invoice Charges Work:</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>If threshold is 0 or charges are disabled, all invoices are free</li>
                            <li>If invoice amount exceeds the threshold, charges are calculated</li>
                            <li>Charges = (Amount × Percentage%) + Fixed Amount</li>
                            <li>Charges are deducted from the business balance when invoice is paid</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90 flex items-center">
                    <i class="fas fa-save mr-2"></i> Save Invoice Charge Settings
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

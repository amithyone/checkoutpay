@extends('layouts.business')

@section('title', 'Settings')
@section('page-title', 'Settings')

@section('content')
@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    </div>
@endif

<div class="space-y-6">
    <!-- Profile Picture & Basic Info -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">Profile Picture</h3>
        
        <form method="POST" action="{{ route('business.settings.update') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @method('PUT')
            
            <div class="flex items-start gap-6">
                <div class="flex-shrink-0">
                    @php
                        $profilePicture = null;
                        if ($business->profile_picture) {
                            $profilePath = storage_path('app/public/' . $business->profile_picture);
                            if (file_exists($profilePath)) {
                                $profilePicture = asset('storage/' . $business->profile_picture);
                            }
                        }
                    @endphp
                    @if($profilePicture)
                        <img src="{{ $profilePicture }}?v={{ time() }}" alt="Profile Picture" 
                            class="w-24 h-24 rounded-full object-cover border-2 border-gray-200">
                    @else
                        <div class="w-24 h-24 rounded-full bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center text-white text-3xl font-bold border-2 border-gray-200">
                            {{ strtoupper(substr($business->name, 0, 1)) }}
                        </div>
                    @endif
                </div>
                
                <div class="flex-1">
                    <div>
                        <label for="profile_picture" class="block text-sm font-medium text-gray-700 mb-2">Upload Profile Picture</label>
                        <input type="file" name="profile_picture" id="profile_picture" accept="image/*"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary/90">
                        <p class="mt-1 text-xs text-gray-500">JPG, PNG or GIF. Max size: 2MB</p>
                        @error('profile_picture')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    @if($business->profile_picture)
                    <div class="mt-4">
                        <form method="POST" action="{{ route('business.settings.remove-profile-picture') }}" onsubmit="return confirm('Are you sure you want to remove your profile picture?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 hover:text-red-800">
                                <i class="fas fa-trash mr-1"></i> Remove Profile Picture
                            </button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>
            
            <div>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    <i class="fas fa-save mr-2"></i> Save Profile Picture
                </button>
            </div>
        </form>
    </div>

    <!-- Notification Preferences -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">
            <i class="fas fa-bell mr-2"></i> Notification Preferences
        </h3>
        
        <form method="POST" action="{{ route('business.settings.update') }}">
            @csrf
            @method('PUT')
            
            <div class="space-y-4">
                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                    <div class="flex-1">
                        <label class="text-sm font-medium text-gray-900">Email Notifications</label>
                        <p class="text-xs text-gray-500 mt-1">Receive notifications via email</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="notifications_email_enabled" value="1" 
                            {{ old('notifications_email_enabled', $business->notifications_email_enabled ?? true) ? 'checked' : '' }}
                            class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                    <div class="flex-1">
                        <label class="text-sm font-medium text-gray-900">Payment Notifications</label>
                        <p class="text-xs text-gray-500 mt-1">Get notified when payments are received</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="notifications_payment_enabled" value="1" 
                            {{ old('notifications_payment_enabled', $business->notifications_payment_enabled ?? true) ? 'checked' : '' }}
                            class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                    <div class="flex-1">
                        <label class="text-sm font-medium text-gray-900">Withdrawal Notifications</label>
                        <p class="text-xs text-gray-500 mt-1">Get notified about withdrawal requests and approvals</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="notifications_withdrawal_enabled" value="1" 
                            {{ old('notifications_withdrawal_enabled', $business->notifications_withdrawal_enabled ?? true) ? 'checked' : '' }}
                            class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                    <div class="flex-1">
                        <label class="text-sm font-medium text-gray-900">Website Notifications</label>
                        <p class="text-xs text-gray-500 mt-1">Get notified when websites are approved or added</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="notifications_website_enabled" value="1" 
                            {{ old('notifications_website_enabled', $business->notifications_website_enabled ?? true) ? 'checked' : '' }}
                            class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                    <div class="flex-1">
                        <label class="text-sm font-medium text-gray-900">Security Notifications</label>
                        <p class="text-xs text-gray-500 mt-1">Get notified about security events (password changes, etc.)</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="notifications_security_enabled" value="1" 
                            {{ old('notifications_security_enabled', $business->notifications_security_enabled ?? true) ? 'checked' : '' }}
                            class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                    </label>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    <i class="fas fa-save mr-2"></i> Save Notification Preferences
                </button>
            </div>
        </form>
    </div>

    <!-- Telegram Notifications -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">
            <i class="fab fa-telegram mr-2"></i> Telegram Notifications
        </h3>
        
        <form method="POST" action="{{ route('business.settings.update') }}">
            @csrf
            @method('PUT')
            
            <div class="space-y-6">
                <!-- Telegram Configuration -->
                <div class="border border-gray-200 rounded-lg p-4 space-y-4">
                    <h4 class="text-sm font-medium text-gray-900 mb-3">Telegram Configuration</h4>
                    
                    <div>
                        <label for="telegram_bot_token" class="block text-sm font-medium text-gray-700 mb-1">Bot Token</label>
                        <input type="text" name="telegram_bot_token" id="telegram_bot_token" 
                            value="{{ old('telegram_bot_token', $business->telegram_bot_token) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary font-mono text-sm"
                            placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz">
                        <p class="mt-1 text-xs text-gray-500">Get your bot token from <a href="https://t.me/BotFather" target="_blank" class="text-primary hover:underline">@BotFather</a></p>
                        @error('telegram_bot_token')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="telegram_chat_id" class="block text-sm font-medium text-gray-700 mb-1">Chat ID</label>
                        <input type="text" name="telegram_chat_id" id="telegram_chat_id" 
                            value="{{ old('telegram_chat_id', $business->telegram_chat_id) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary font-mono text-sm"
                            placeholder="123456789">
                        <p class="mt-1 text-xs text-gray-500">Get your chat ID by messaging <a href="https://t.me/userinfobot" target="_blank" class="text-primary hover:underline">@userinfobot</a></p>
                        @error('telegram_chat_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Telegram Notification Types -->
                <div class="space-y-4">
                    <h4 class="text-sm font-medium text-gray-900">Notification Types</h4>
                    
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div class="flex-1">
                            <label class="text-sm font-medium text-gray-900">Payment Notifications</label>
                            <p class="text-xs text-gray-500 mt-1">Get notified via Telegram when payments are received</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="telegram_payment_enabled" value="1" 
                                {{ old('telegram_payment_enabled', $business->telegram_payment_enabled ?? false) ? 'checked' : '' }}
                                class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div class="flex-1">
                            <label class="text-sm font-medium text-gray-900">Withdrawal Notifications</label>
                            <p class="text-xs text-gray-500 mt-1">Get notified via Telegram about withdrawal requests and approvals</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="telegram_withdrawal_enabled" value="1" 
                                {{ old('telegram_withdrawal_enabled', $business->telegram_withdrawal_enabled ?? false) ? 'checked' : '' }}
                                class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div class="flex-1">
                            <label class="text-sm font-medium text-gray-900">Security Notifications</label>
                            <p class="text-xs text-gray-500 mt-1">Get notified via Telegram about security events (password changes, etc.)</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="telegram_security_enabled" value="1" 
                                {{ old('telegram_security_enabled', $business->telegram_security_enabled ?? false) ? 'checked' : '' }}
                                class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div class="flex-1">
                            <label class="text-sm font-medium text-gray-900">Login Notifications</label>
                            <p class="text-xs text-gray-500 mt-1">Get notified via Telegram when you log in</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="telegram_login_enabled" value="1" 
                                {{ old('telegram_login_enabled', $business->telegram_login_enabled ?? false) ? 'checked' : '' }}
                                class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                        </label>
                    </div>

                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    <i class="fas fa-save mr-2"></i> Save Telegram Settings
                </button>
            </div>
        </form>
    </div>

    <!-- General Settings -->
    <div id="auto-withdraw" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">
            <i class="fas fa-cog mr-2"></i> General Settings
        </h3>
        
        <form method="POST" action="{{ route('business.settings.update') }}">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
                    <select name="timezone" id="timezone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <option value="Africa/Lagos" {{ old('timezone', $business->timezone ?? 'Africa/Lagos') == 'Africa/Lagos' ? 'selected' : '' }}>Africa/Lagos (WAT)</option>
                        <option value="Africa/Abidjan" {{ old('timezone', $business->timezone ?? 'Africa/Lagos') == 'Africa/Abidjan' ? 'selected' : '' }}>Africa/Abidjan (GMT)</option>
                        <option value="Africa/Cairo" {{ old('timezone', $business->timezone ?? 'Africa/Lagos') == 'Africa/Cairo' ? 'selected' : '' }}>Africa/Cairo (EET)</option>
                        <option value="Africa/Johannesburg" {{ old('timezone', $business->timezone ?? 'Africa/Lagos') == 'Africa/Johannesburg' ? 'selected' : '' }}>Africa/Johannesburg (SAST)</option>
                        <option value="UTC" {{ old('timezone', $business->timezone ?? 'Africa/Lagos') == 'UTC' ? 'selected' : '' }}>UTC</option>
                    </select>
                    @error('timezone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="currency" class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                    <select name="currency" id="currency" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <option value="NGN" {{ old('currency', $business->currency ?? 'NGN') == 'NGN' ? 'selected' : '' }}>NGN - Nigerian Naira</option>
                        <option value="USD" {{ old('currency', $business->currency ?? 'NGN') == 'USD' ? 'selected' : '' }}>USD - US Dollar</option>
                        <option value="GBP" {{ old('currency', $business->currency ?? 'NGN') == 'GBP' ? 'selected' : '' }}>GBP - British Pound</option>
                        <option value="EUR" {{ old('currency', $business->currency ?? 'NGN') == 'EUR' ? 'selected' : '' }}>EUR - Euro</option>
                    </select>
                    @error('currency')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="auto_withdraw_threshold" class="block text-sm font-medium text-gray-700 mb-1">Auto-Withdraw Threshold</label>
                    <input type="number" name="auto_withdraw_threshold" id="auto_withdraw_threshold" 
                        value="{{ old('auto_withdraw_threshold', $business->auto_withdraw_threshold) }}"
                        step="0.01" min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        placeholder="0.00">
                    <p class="mt-1 text-xs text-gray-500">Automatically request withdrawal when balance reaches this amount (leave empty to disable). You must <strong>save a withdrawal account</strong> first (on Withdrawals → Request Withdrawal, check &quot;Save this account&quot;).</p>
                    @error('auto_withdraw_threshold')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input type="hidden" name="auto_withdraw_end_of_day" value="0">
                        <input type="checkbox" name="auto_withdraw_end_of_day" id="auto_withdraw_end_of_day" value="1"
                            {{ old('auto_withdraw_end_of_day', $business->auto_withdraw_end_of_day ?? false) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-primary focus:ring-primary">
                    </div>
                    <div class="ml-3">
                        <label for="auto_withdraw_end_of_day" class="text-sm font-medium text-gray-700">Withdraw at end of day (5pm)</label>
                        <p class="text-xs text-gray-500 mt-0.5">If enabled, auto-withdrawal runs once daily at 5pm instead of immediately when balance reaches threshold.</p>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    <i class="fas fa-save mr-2"></i> Save General Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Security Settings -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">
            <i class="fas fa-shield-alt mr-2"></i> Security Settings
        </h3>
        
        <div class="space-y-6">
            <!-- Two-Factor Authentication -->
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex-1">
                        <h4 class="text-sm font-medium text-gray-900">Two-Factor Authentication (2FA)</h4>
                        <p class="text-xs text-gray-500 mt-1">Add an extra layer of security to your account</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($business->two_factor_enabled)
                            <span class="px-3 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                <i class="fas fa-check-circle mr-1"></i> Enabled
                            </span>
                        @else
                            <span class="px-3 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">
                                Disabled
                            </span>
                        @endif
                    </div>
                </div>
                
                @if($business->two_factor_enabled)
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <p class="text-sm text-blue-800 mb-3">
                            <i class="fas fa-info-circle mr-2"></i>
                            Two-factor authentication is currently enabled. You'll need to provide a verification code when logging in.
                        </p>
                        <form method="POST" action="{{ route('business.settings.2fa.disable') }}" onsubmit="return confirm('Are you sure you want to disable 2FA? You will need to enter your verification code to confirm.')">
                            @csrf
                            <div class="flex items-center gap-2">
                                <input type="text" name="code" placeholder="Enter 6-digit code" maxlength="6" pattern="[0-9]{6}" required
                                    class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm">
                                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                                    <i class="fas fa-times mr-1"></i> Disable 2FA
                                </button>
                            </div>
                            @error('code')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </form>
                    </div>
                @else
                    <div>
                        <a href="{{ route('business.settings.2fa.setup') }}" class="inline-block px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm">
                            <i class="fas fa-shield-alt mr-2"></i> Set Up Two-Factor Authentication
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Websites Portfolio -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">Websites Portfolio</h3>
            <a href="{{ route('business.websites.index') }}" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                <i class="fas fa-cog mr-2"></i> Manage Websites
            </a>
        </div>
        
        @php
            $websites = $business->websites;
            $approvedCount = $business->approvedWebsites->count();
        @endphp
        
        @if($websites->count() > 0)
            <div class="space-y-3 mb-4">
                @foreach($websites->take(3) as $website)
                    <div class="border border-gray-200 rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <a href="{{ $website->website_url }}" target="_blank" 
                                    class="text-primary hover:underline font-medium text-sm">
                                    {{ $website->website_url }}
                                    <i class="fas fa-external-link-alt text-xs ml-1"></i>
                                </a>
                                <div class="mt-1">
                                    @if($website->is_approved)
                                        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                            <i class="fas fa-check-circle mr-1"></i> Approved
                                        </span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                            <i class="fas fa-clock mr-1"></i> Pending
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
                @if($websites->count() > 3)
                    <p class="text-xs text-gray-500 text-center">
                        + {{ $websites->count() - 3 }} more website(s). 
                        <a href="{{ route('business.websites.index') }}" class="text-primary hover:underline">View all</a>
                    </p>
                @endif
            </div>
            
            <div class="border-t pt-4">
                @if($approvedCount > 0)
                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">
                            <i class="fas fa-check-circle mr-2"></i> {{ $approvedCount }} Approved Website(s)
                        </span>
                        <p class="text-sm text-gray-600">You can request account numbers.</p>
                    </div>
                @else
                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 text-sm font-medium bg-yellow-100 text-yellow-800 rounded-full">
                            <i class="fas fa-clock mr-2"></i> Pending Approval
                        </span>
                        <p class="text-sm text-gray-600">Waiting for admin approval.</p>
                    </div>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mt-3">
                        <p class="text-sm text-yellow-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Note:</strong> You need at least one approved website before you can request account numbers. Our team will review your websites and notify you once approved.
                        </p>
                    </div>
                @endif
            </div>
        @else
            <div class="text-center py-6 text-gray-500">
                <i class="fas fa-globe text-3xl mb-2 text-gray-300"></i>
                <p class="text-sm mb-3">No websites added yet.</p>
                <a href="{{ route('business.websites.index') }}" class="text-primary hover:underline text-sm">
                    Add your first website →
                </a>
            </div>
        @endif
    </div>

    <!-- Webhook Settings -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">Webhook Settings</h3>
        
        <form method="POST" action="{{ route('business.settings.update') }}">
            @csrf
            @method('PUT')

            <div>
                <label for="webhook_url" class="block text-sm font-medium text-gray-700 mb-1">Webhook URL</label>
                <input type="url" name="webhook_url" id="webhook_url" value="{{ old('webhook_url', $business->webhook_url) }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                    placeholder="https://your-domain.com/webhook">
                <p class="mt-1 text-xs text-gray-500">We'll send payment notifications to this URL</p>
                @error('webhook_url')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-6">
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    <i class="fas fa-save mr-2"></i> Save Webhook Settings
                </button>
            </div>
        </form>
    </div>

    <!-- API Key -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">API Key</h3>
        
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Your API Key</label>
                <div class="flex items-center gap-2">
                    <input type="text" value="{{ $business->api_key }}" readonly
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 font-mono text-sm"
                        id="api-key-input">
                    <button type="button" onclick="copyApiKey()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                        <i class="fas fa-copy mr-2"></i> Copy
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-500">Keep this key secure. Don't share it publicly.</p>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-sm text-yellow-800">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Warning:</strong> Regenerating your API key will invalidate the current key. Make sure to update it in all your integrations.
                </p>
            </div>

            <form method="POST" action="{{ route('business.settings.regenerate-api-key') }}" onsubmit="return confirm('Are you sure you want to regenerate your API key? This will invalidate your current key.')">
                @csrf
                @method('POST')
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    <i class="fas fa-sync-alt mr-2"></i> Regenerate API Key
                </button>
            </form>
        </div>
    </div>

    <!-- Account Information -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">Account Information</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Business Name</label>
                <p class="text-sm text-gray-900">{{ $business->name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <p class="text-sm text-gray-900">{{ $business->email }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account Status</label>
                <p class="text-sm">
                    @if($business->is_active)
                        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                    @else
                        <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Inactive</span>
                    @endif
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Current Balance</label>
                <p class="text-sm font-semibold text-gray-900">₦{{ number_format($business->balance, 2) }}</p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function copyApiKey() {
    const input = document.getElementById('api-key-input');
    input.select();
    input.setSelectionRange(0, 99999); // For mobile devices
    
    navigator.clipboard.writeText(input.value).then(function() {
        // Show success message
        const message = document.createElement('div');
        message.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
        message.textContent = 'API key copied to clipboard!';
        document.body.appendChild(message);
        
        setTimeout(() => {
            message.remove();
        }, 2000);
    }).catch(function(err) {
        alert('Failed to copy. Please copy manually.');
    });
}
</script>
@endpush
@endsection

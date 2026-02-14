<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    public function index()
    {
        $business = Auth::guard('business')->user();
        return view('business.settings.index', compact('business'));
    }

    public function update(Request $request)
    {
        $business = Auth::guard('business')->user();

        $validated = $request->validate([
            'webhook_url' => 'nullable|url|max:500',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'notifications_email_enabled' => 'boolean',
            'notifications_payment_enabled' => 'boolean',
            'notifications_withdrawal_enabled' => 'boolean',
            'notifications_website_enabled' => 'boolean',
            'notifications_security_enabled' => 'boolean',
            'telegram_bot_token' => 'nullable|string|max:255',
            'telegram_chat_id' => 'nullable|string|max:50',
            'telegram_withdrawal_enabled' => 'boolean',
            'telegram_security_enabled' => 'boolean',
            'telegram_payment_enabled' => 'boolean',
            'telegram_login_enabled' => 'boolean',
            'telegram_admin_login_enabled' => 'boolean',
            'timezone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|size:3',
            'auto_withdraw_threshold' => 'nullable|numeric|min:0',
            'auto_withdraw_end_of_day' => 'boolean',
            'two_factor_enabled' => 'boolean',
        ]);

        // Handle profile picture upload
        if ($request->hasFile('profile_picture')) {
            // Delete old profile picture if exists
            if ($business->profile_picture && Storage::disk('public')->exists($business->profile_picture)) {
                Storage::disk('public')->delete($business->profile_picture);
            }

            // Store new profile picture
            $path = $request->file('profile_picture')->store('businesses/profile-pictures', 'public');
            $validated['profile_picture'] = $path;
        } else {
            // Remove profile_picture from validated if not uploaded
            unset($validated['profile_picture']);
        }

        // Handle boolean fields that might not be sent (checkboxes)
        // If checkbox is unchecked, it won't be in the request, so we default to false
        $booleanFields = [
            'notifications_email_enabled',
            'notifications_payment_enabled',
            'notifications_withdrawal_enabled',
            'notifications_website_enabled',
            'notifications_security_enabled',
            'telegram_withdrawal_enabled',
            'telegram_security_enabled',
            'telegram_payment_enabled',
            'telegram_login_enabled',
            'telegram_admin_login_enabled',
            'auto_withdraw_end_of_day',
            'two_factor_enabled',
        ];

        foreach ($booleanFields as $field) {
            // Check if the field exists in request and has a truthy value
            if ($request->has($field) && $request->input($field)) {
                $validated[$field] = true;
            } else {
                $validated[$field] = false;
            }
        }

        // Require saved withdrawal account to enable auto-withdrawal (threshold > 0)
        $threshold = isset($validated['auto_withdraw_threshold']) ? (float) $validated['auto_withdraw_threshold'] : null;
        if ($threshold > 0 && !$business->hasSavedWithdrawalAccount()) {
            $validated['auto_withdraw_threshold'] = null;
            return redirect()->route('business.settings.index')
                ->with('error', 'Save a withdrawal account first to enable auto-withdrawal. On the Withdrawals page, request a withdrawal and check "Save this account for future withdrawals".')
                ->withInput();
        }

        $business->update($validated);

        return redirect()->route('business.settings.index')
            ->with('success', 'Settings updated successfully');
    }

    public function removeProfilePicture()
    {
        $business = Auth::guard('business')->user();

        if ($business->profile_picture && Storage::disk('public')->exists($business->profile_picture)) {
            Storage::disk('public')->delete($business->profile_picture);
        }

        $business->update(['profile_picture' => null]);

        return redirect()->route('business.settings.index')
            ->with('success', 'Profile picture removed successfully');
    }

    public function regenerateApiKey()
    {
        $business = Auth::guard('business')->user();
        
        $business->update([
            'api_key' => 'pk_' . Str::random(32),
        ]);

        return redirect()->route('business.settings.index')
            ->with('success', 'API key regenerated successfully');
    }

    public function setupTwoFactor()
    {
        $business = Auth::guard('business')->user();

        // Generate secret if not exists
        if (!$business->two_factor_secret) {
            $business->update([
                'two_factor_secret' => $business->generateTwoFactorSecret(),
            ]);
            $business->refresh();
        }

        $qrCodeUrl = $business->getTwoFactorQrCodeUrl();
        $appName = \App\Models\Setting::get('site_name', 'CheckoutPay');

        return view('business.settings.two-factor-setup', compact('business', 'qrCodeUrl', 'appName'));
    }

    public function verifyTwoFactorSetup(Request $request)
    {
        $business = Auth::guard('business')->user();

        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        if ($business->verifyTwoFactorCode($validated['code'])) {
            $business->update([
                'two_factor_enabled' => true,
            ]);

            return redirect()->route('business.settings.index')
                ->with('success', 'Two-factor authentication enabled successfully');
        }

        return back()->withErrors([
            'code' => 'Invalid verification code. Please try again.',
        ]);
    }

    public function disableTwoFactor(Request $request)
    {
        $business = Auth::guard('business')->user();

        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        if (!$business->verifyTwoFactorCode($validated['code'])) {
            return back()->withErrors([
                'code' => 'Invalid verification code. Cannot disable 2FA.',
            ]);
        }

        $business->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
        ]);

        return redirect()->route('business.settings.index')
            ->with('success', 'Two-factor authentication disabled successfully');
    }
}

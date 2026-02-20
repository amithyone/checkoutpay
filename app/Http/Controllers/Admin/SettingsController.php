<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\WhitelistedEmailAddress;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Display settings page
     */
    public function index()
    {
        $groups = [
            'payment' => 'Payment Settings',
            'email' => 'Email Settings',
            'general' => 'General Settings',
            'security' => 'Security Settings',
            'charges' => 'Charge Settings',
        ];

        $settings = [];
        foreach ($groups as $group => $label) {
            $settings[$group] = Setting::getByGroup($group);
        }

        // Get whitelisted emails for display
        $whitelistedEmails = WhitelistedEmailAddress::orderBy('created_at', 'desc')->get();

        return view('admin.settings.index', compact('settings', 'groups', 'whitelistedEmails'));
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
        // Validate payment settings if they are in the request
        if ($request->has('payment_time_window_minutes') && $request->has('transaction_pending_time_minutes')) {
            $validated = $request->validate([
                'payment_time_window_minutes' => 'required|integer|min:1|max:1440', // Max 24 hours
                'transaction_pending_time_minutes' => 'required|integer|min:5|max:10080', // 5 minutes to 7 days
            ]);

            // Update payment time window (for email matching)
            Setting::set(
                'payment_time_window_minutes',
                $validated['payment_time_window_minutes'],
                'integer',
                'payment',
                'Maximum time window (in minutes) for matching emails with payment requests. Emails received after this time will not be matched.'
            );

            // Update transaction pending time (expiration time)
            Setting::set(
                'transaction_pending_time_minutes',
                $validated['transaction_pending_time_minutes'],
                'integer',
                'payment',
                'Time (in minutes) before a pending transaction expires. After expiration, transaction will be automatically marked as expired and cannot be matched.'
            );
        }

        // Update IMAP fetching setting
        // Check if checkbox was submitted (either checked or unchecked)
        $disableImap = $request->has('disable_imap_fetching') && $request->input('disable_imap_fetching') == '1';
        Setting::set(
            'disable_imap_fetching',
            $disableImap ? 1 : 0,
            'boolean',
            'email',
            'Disable IMAP email fetching. When enabled, only direct filesystem reading will be used. Recommended for shared hosting where direct filesystem access is more reliable.'
        );

        // Update default charge settings
        if ($request->has('default_charge_percentage')) {
            Setting::set(
                'default_charge_percentage',
                $request->default_charge_percentage,
                'float',
                'charges',
                'Default percentage charge applied to all payments'
            );
        }

        if ($request->has('default_charge_fixed')) {
            Setting::set(
                'default_charge_fixed',
                $request->default_charge_fixed,
                'float',
                'charges',
                'Default fixed charge amount added to all payments'
            );
        }

        // Update invoice charge settings
        if ($request->has('invoice_charge_threshold')) {
            Setting::set(
                'invoice_charge_threshold',
                $request->invoice_charge_threshold,
                'float',
                'charges',
                'Invoice amount threshold above which charges apply. 0 = free for all invoices.'
            );
        }

        if ($request->has('invoice_charge_percentage')) {
            Setting::set(
                'invoice_charge_percentage',
                $request->invoice_charge_percentage,
                'float',
                'charges',
                'Percentage charge for invoice payments above threshold'
            );
        }

        if ($request->has('invoice_charge_fixed')) {
            Setting::set(
                'invoice_charge_fixed',
                $request->invoice_charge_fixed,
                'float',
                'charges',
                'Fixed charge amount for invoice payments above threshold'
            );
        }

        if ($request->has('invoice_charges_enabled')) {
            Setting::set(
                'invoice_charges_enabled',
                $request->invoice_charges_enabled == '1',
                'boolean',
                'charges',
                'Enable or disable charges for invoice payments'
            );
        }

        // Admin withdrawal notifications (Telegram + email per withdrawal request)
        if ($request->has('admin_withdrawal_notification_email') || $request->has('admin_telegram_bot_token') || $request->has('admin_telegram_chat_id')) {
            if ($request->has('admin_withdrawal_notification_email')) {
                Setting::set(
                    'admin_withdrawal_notification_email',
                    $request->admin_withdrawal_notification_email ?: null,
                    'string',
                    'notifications',
                    'Admin email to receive a notification on each withdrawal request'
                );
            }
            if ($request->has('admin_telegram_bot_token')) {
                Setting::set(
                    'admin_telegram_bot_token',
                    $request->admin_telegram_bot_token ?: null,
                    'string',
                    'notifications',
                    'Telegram bot token for admin withdrawal alerts'
                );
            }
            if ($request->has('admin_telegram_chat_id')) {
                Setting::set(
                    'admin_telegram_chat_id',
                    $request->admin_telegram_chat_id ?: null,
                    'string',
                    'notifications',
                    'Telegram chat ID for admin withdrawal alerts'
                );
            }
        }

        return redirect()->route('admin.settings.index')
            ->with('success', 'Settings updated successfully!');
    }

    /**
     * Add whitelisted email from settings page
     */
    public function addWhitelistedEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|max:255|unique:whitelisted_email_addresses,email',
            'description' => 'nullable|string|max:500',
        ]);

        WhitelistedEmailAddress::create([
            'email' => strtolower(trim($validated['email'])),
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('admin.settings.index')
            ->with('success', 'Whitelisted email added successfully!');
    }

    /**
     * Remove whitelisted email from settings page
     */
    public function removeWhitelistedEmail(WhitelistedEmailAddress $whitelistedEmail)
    {
        $whitelistedEmail->delete();
        
        return redirect()->route('admin.settings.index')
            ->with('success', 'Whitelisted email removed successfully!');
    }

    /**
     * Update general settings (logo, favicon, contact info)
     */
    public function updateGeneral(Request $request)
    {
        $validated = $request->validate([
            'site_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_address' => 'nullable|string|max:500',
            'show_beta_badge' => 'nullable|boolean',
            'rentals_accent_color' => 'nullable|string|max:7',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
            'admin_logo' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
            'business_logo' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
            'favicon' => 'nullable|image|mimes:png,ico|max:512',
            'email_logo' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        // Handle site logo upload (for landing pages)
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $logoPath = $logo->store('settings', 'public');
            Setting::set('site_logo', $logoPath, 'string', 'general', 'Site logo (for landing pages)');
        }

        // Handle admin logo upload
        if ($request->hasFile('admin_logo')) {
            $adminLogo = $request->file('admin_logo');
            $adminLogoPath = $adminLogo->store('settings', 'public');
            Setting::set('admin_logo', $adminLogoPath, 'string', 'general', 'Admin panel logo');
        }

        // Handle business logo upload
        if ($request->hasFile('business_logo')) {
            $businessLogo = $request->file('business_logo');
            $businessLogoPath = $businessLogo->store('settings', 'public');
            Setting::set('business_logo', $businessLogoPath, 'string', 'general', 'Business dashboard logo');
        }

        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            $favicon = $request->file('favicon');
            $faviconPath = $favicon->store('settings', 'public');
            Setting::set('site_favicon', $faviconPath, 'string', 'general', 'Site favicon');
        }

        // Handle email logo upload
        if ($request->hasFile('email_logo')) {
            $emailLogo = $request->file('email_logo');
            $emailLogoPath = $emailLogo->store('settings', 'public');
            Setting::set('email_logo', $emailLogoPath, 'string', 'general', 'Black logo for email templates');
        }

        // Update contact information
        if (isset($validated['site_name'])) {
            Setting::set('site_name', $validated['site_name'], 'string', 'general', 'Site name');
        }
        if (isset($validated['contact_email'])) {
            Setting::set('contact_email', $validated['contact_email'], 'string', 'general', 'Contact email');
        }
        if (isset($validated['contact_phone'])) {
            Setting::set('contact_phone', $validated['contact_phone'], 'string', 'general', 'Contact phone');
        }
        if (isset($validated['contact_address'])) {
            Setting::set('contact_address', $validated['contact_address'], 'string', 'general', 'Contact address');
        }
        
        // Update beta badge setting (only if ENV variable is not set)
        if (env('SHOW_BETA_BADGE') === null) {
            $showBetaBadge = $request->has('show_beta_badge') && $request->input('show_beta_badge') == '1';
            Setting::set('show_beta_badge', $showBetaBadge ? '1' : '0', 'boolean', 'general', 'Show floating beta badge on all pages');
        }

        // Rentals page accent color (search bar, filter, category pills, cart icon)
        $rentalsColor = $validated['rentals_accent_color'] ?? null;
        if ($rentalsColor && preg_match('/^#[0-9A-Fa-f]{6}$/', $rentalsColor)) {
            Setting::set('rentals_accent_color', $rentalsColor, 'string', 'general', 'Rentals page accent color (search bar, filters, category pills, cart icon)');
        }

        return redirect()->route('admin.settings.index')
            ->with('success', 'General settings updated successfully!');
    }
}

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
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
            'favicon' => 'nullable|image|mimes:png,ico|max:512',
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $logoPath = $logo->store('settings', 'public');
            Setting::set('site_logo', $logoPath, 'string', 'general', 'Site logo');
        }

        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            $favicon = $request->file('favicon');
            $faviconPath = $favicon->store('settings', 'public');
            Setting::set('site_favicon', $faviconPath, 'string', 'general', 'Site favicon');
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

        return redirect()->route('admin.settings.index')
            ->with('success', 'General settings updated successfully!');
    }
}

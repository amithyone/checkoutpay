<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\WhitelistedEmailAddress;
use App\Models\ZapierLog;
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
        
        // Get Zapier logs statistics
        $zapierStats = [
            'total' => ZapierLog::count(),
            'today' => ZapierLog::whereDate('created_at', today())->count(),
            'matched' => ZapierLog::where('status', 'matched')->count(),
            'error' => ZapierLog::where('status', 'error')->count(),
            'last_log' => ZapierLog::latest()->first(),
        ];

        return view('admin.settings.index', compact('settings', 'groups', 'whitelistedEmails', 'zapierStats'));
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'payment_time_window_minutes' => 'required|integer|min:1|max:1440', // Max 24 hours
            'zapier_webhook_secret' => 'nullable|string|min:16|max:255',
        ]);

        // Update payment time window
        Setting::set(
            'payment_time_window_minutes',
            $validated['payment_time_window_minutes'],
            'integer',
            'payment',
            'Maximum time window (in minutes) for matching emails with payment requests. Emails received after this time will not be matched.'
        );

        // Update Zapier webhook secret (always update, even if empty to allow clearing)
        Setting::set(
            'zapier_webhook_secret',
            $validated['zapier_webhook_secret'] ?? '',
            'string',
            'security',
            'Secret key for authenticating Zapier webhook requests. Add this as a header "X-Zapier-Secret" in your Zapier webhook action.'
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
}

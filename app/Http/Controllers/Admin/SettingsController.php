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

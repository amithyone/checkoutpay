<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
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

        return view('admin.settings.index', compact('settings', 'groups'));
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

        // Update Zapier webhook secret (if provided)
        if (isset($validated['zapier_webhook_secret'])) {
            Setting::set(
                'zapier_webhook_secret',
                $validated['zapier_webhook_secret'],
                'string',
                'security',
                'Secret key for authenticating Zapier webhook requests. Add this as a header "X-Zapier-Secret" in your Zapier webhook action.'
            );
        }

        return redirect()->route('admin.settings.index')
            ->with('success', 'Settings updated successfully!');
    }
}

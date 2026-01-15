<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class EmailTemplateController extends Controller
{
    /**
     * List all available email templates
     */
    public function index()
    {
        $templates = [
            'business-verification' => [
                'name' => 'Business Email Verification',
                'description' => 'Sent when a business registers and needs to verify their email',
                'subject' => 'Verify Your Email Address',
            ],
            'login-notification' => [
                'name' => 'Login Notification',
                'description' => 'Sent when a business logs into their account',
                'subject' => 'New Login Detected',
            ],
            'new-deposit' => [
                'name' => 'New Deposit Notification',
                'description' => 'Sent when a payment is successfully matched and approved',
                'subject' => 'Payment Received',
            ],
            'website-approved' => [
                'name' => 'Website Approved',
                'description' => 'Sent when a business website is approved by admin',
                'subject' => 'Website Approved',
            ],
            'website-added' => [
                'name' => 'Website Added',
                'description' => 'Sent when a new website is added to a business portfolio',
                'subject' => 'New Website Added',
            ],
            'withdrawal-requested' => [
                'name' => 'Withdrawal Requested',
                'description' => 'Sent when a business submits a withdrawal request',
                'subject' => 'Withdrawal Request Submitted',
            ],
            'withdrawal-approved' => [
                'name' => 'Withdrawal Approved',
                'description' => 'Sent when a withdrawal request is approved',
                'subject' => 'Withdrawal Approved',
            ],
            'password-changed' => [
                'name' => 'Password Changed',
                'description' => 'Sent when a business changes their password',
                'subject' => 'Password Changed',
            ],
        ];

        // Get custom templates from database
        $customTemplates = [];
        foreach ($templates as $key => $template) {
            $customTemplates[$key] = [
                'name' => $template['name'],
                'description' => $template['description'],
                'subject' => Setting::get("email_template_{$key}_subject", $template['subject']),
                'has_custom' => Setting::get("email_template_{$key}_custom", false),
            ];
        }

        return view('admin.email-templates.index', compact('customTemplates'));
    }

    /**
     * Show edit form for a specific email template
     */
    public function edit($template)
    {
        $templates = [
            'business-verification' => [
                'name' => 'Business Email Verification',
                'description' => 'Sent when a business registers and needs to verify their email',
                'default_subject' => 'Verify Your Email Address',
            ],
            'login-notification' => [
                'name' => 'Login Notification',
                'description' => 'Sent when a business logs into their account',
                'default_subject' => 'New Login Detected',
            ],
            'new-deposit' => [
                'name' => 'New Deposit Notification',
                'description' => 'Sent when a payment is successfully matched and approved',
                'default_subject' => 'Payment Received',
            ],
            'website-approved' => [
                'name' => 'Website Approved',
                'description' => 'Sent when a business website is approved by admin',
                'default_subject' => 'Website Approved',
            ],
            'website-added' => [
                'name' => 'Website Added',
                'description' => 'Sent when a new website is added to a business portfolio',
                'default_subject' => 'New Website Added',
            ],
            'withdrawal-requested' => [
                'name' => 'Withdrawal Requested',
                'description' => 'Sent when a business submits a withdrawal request',
                'default_subject' => 'Withdrawal Request Submitted',
            ],
            'withdrawal-approved' => [
                'name' => 'Withdrawal Approved',
                'description' => 'Sent when a withdrawal request is approved',
                'default_subject' => 'Withdrawal Approved',
            ],
            'password-changed' => [
                'name' => 'Password Changed',
                'description' => 'Sent when a business changes their password',
                'default_subject' => 'Password Changed',
            ],
        ];

        if (!isset($templates[$template])) {
            return redirect()->route('admin.email-templates.index')
                ->with('error', 'Template not found.');
        }

        $templateInfo = $templates[$template];

        // Get current template content from database or default Blade file
        $customContent = Setting::get("email_template_{$template}_content", null);
        $customSubject = Setting::get("email_template_{$template}_subject", $templateInfo['default_subject']);
        $isCustom = Setting::get("email_template_{$template}_custom", false);

        // If no custom content, load from Blade file
        if (!$customContent) {
            $bladePath = resource_path("views/emails/{$template}.blade.php");
            if (File::exists($bladePath)) {
                $customContent = File::get($bladePath);
            } else {
                $customContent = '';
            }
        }

        // Available variables for each template
        $availableVariables = $this->getAvailableVariables($template);

        return view('admin.email-templates.edit', compact(
            'template',
            'templateInfo',
            'customContent',
            'customSubject',
            'isCustom',
            'availableVariables'
        ));
    }

    /**
     * Update email template
     */
    public function update(Request $request, $template)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'use_custom' => 'nullable|boolean',
        ]);

        // Save custom template
        Setting::set(
            "email_template_{$template}_subject",
            $validated['subject'],
            'string',
            'email_templates',
            "Email subject for {$template} template"
        );

        Setting::set(
            "email_template_{$template}_content",
            $validated['content'],
            'text',
            'email_templates',
            "Email content for {$template} template"
        );

        Setting::set(
            "email_template_{$template}_custom",
            $request->has('use_custom') ? true : false,
            'boolean',
            'email_templates',
            "Whether custom template is enabled for {$template}"
        );

        return redirect()->route('admin.email-templates.index')
            ->with('success', 'Email template updated successfully!');
    }

    /**
     * Reset template to default
     */
    public function reset($template)
    {
        Setting::set("email_template_{$template}_custom", false, 'boolean', 'email_templates');
        Setting::set("email_template_{$template}_content", null, 'text', 'email_templates');
        Setting::set("email_template_{$template}_subject", null, 'string', 'email_templates');

        return redirect()->route('admin.email-templates.index')
            ->with('success', 'Template reset to default successfully!');
    }

    /**
     * Preview template
     */
    public function preview($template)
    {
        // This would render the template with sample data
        // For now, just redirect to edit page
        return redirect()->route('admin.email-templates.edit', $template)
            ->with('info', 'Preview functionality coming soon.');
    }

    /**
     * Get available variables for a template
     */
    private function getAvailableVariables($template): array
    {
        $commonVars = [
            '$appName' => 'Application name (e.g., CheckoutPay)',
            '$business->name' => 'Business name',
            '$business->email' => 'Business email address',
        ];

        $templateSpecificVars = match($template) {
            'business-verification' => [
                '$verificationUrl' => 'Email verification URL',
            ],
            'login-notification' => [
                '$ipAddress' => 'IP address of login',
                '$userAgent' => 'Device/browser information',
            ],
            'new-deposit' => [
                '$payment->amount' => 'Payment amount',
                '$payment->reference' => 'Payment reference',
                '$payment->created_at' => 'Payment date',
            ],
            'website-approved' => [
                '$website->website_url' => 'Website URL',
            ],
            'website-added' => [
                '$website->website_url' => 'Website URL',
            ],
            'withdrawal-requested' => [
                '$withdrawal->amount' => 'Withdrawal amount',
                '$withdrawal->bank_name' => 'Bank name',
                '$withdrawal->account_name' => 'Account name',
                '$withdrawal->account_number' => 'Account number',
                '$withdrawal->created_at' => 'Request date',
            ],
            'withdrawal-approved' => [
                '$withdrawal->amount' => 'Withdrawal amount',
                '$withdrawal->bank_name' => 'Bank name',
                '$withdrawal->account_name' => 'Account name',
                '$withdrawal->account_number' => 'Account number',
                '$withdrawal->created_at' => 'Request date',
            ],
            'password-changed' => [
                '$ipAddress' => 'IP address where password was changed',
                '$userAgent' => 'Device/browser information',
            ],
            default => [],
        };

        return array_merge($commonVars, $templateSpecificVars);
    }
}

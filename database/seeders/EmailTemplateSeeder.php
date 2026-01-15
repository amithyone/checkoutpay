<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            'business-verification' => [
                'name' => 'Business Email Verification',
                'subject' => 'Verify Your Email Address',
            ],
            'login-notification' => [
                'name' => 'Login Notification',
                'subject' => 'New Login Detected',
            ],
            'new-deposit' => [
                'name' => 'New Deposit Notification',
                'subject' => 'Payment Received',
            ],
            'website-approved' => [
                'name' => 'Website Approved',
                'subject' => 'Website Approved',
            ],
            'website-added' => [
                'name' => 'Website Added',
                'subject' => 'New Website Added',
            ],
            'withdrawal-requested' => [
                'name' => 'Withdrawal Requested',
                'subject' => 'Withdrawal Request Submitted',
            ],
            'withdrawal-approved' => [
                'name' => 'Withdrawal Approved',
                'subject' => 'Withdrawal Approved',
            ],
            'password-changed' => [
                'name' => 'Password Changed',
                'subject' => 'Password Changed',
            ],
        ];

        foreach ($templates as $key => $template) {
            // Only seed if template doesn't already exist
            $existingSubject = Setting::get("email_template_{$key}_subject", null);
            
            if (!$existingSubject) {
                // Get default template content from Blade file
                $bladePath = resource_path("views/emails/{$key}.blade.php");
                $content = '';
                
                if (File::exists($bladePath)) {
                    $content = File::get($bladePath);
                }
                
                // Store template in database
                Setting::set(
                    "email_template_{$key}_subject",
                    $template['subject'],
                    'string',
                    'email_templates',
                    "Email subject for {$template['name']} template"
                );
                
                if ($content) {
                    Setting::set(
                        "email_template_{$key}_content",
                        $content,
                        'text',
                        'email_templates',
                        "Email content for {$template['name']} template"
                    );
                }
                
                // Set custom flag to false (use default Blade files)
                Setting::set(
                    "email_template_{$key}_custom",
                    false,
                    'boolean',
                    'email_templates',
                    "Whether custom template is enabled for {$template['name']}"
                );
            }
        }
        
        $this->command->info('Email templates seeded successfully!');
    }
}

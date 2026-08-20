<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\EmailTemplateRenderer;
use Illuminate\Console\Command;

/**
 * Install safe custom HTML email templates ({{ $var }} only — no Blade/PHP).
 */
class SeedSafeCustomEmailTemplates extends Command
{
    protected $signature = 'emails:seed-safe-custom-templates {--force : Overwrite existing custom content}';

    protected $description = 'Create/enable safe custom business email templates in settings';

    public function handle(): int
    {
        $dashboardUrl = rtrim((string) config('app.url'), '/').'/business/dashboard';
        $year = (string) now()->year;

        $templates = $this->templates($dashboardUrl, $year);
        $written = 0;

        foreach ($templates as $key => $meta) {
            if (EmailTemplateRenderer::containsForbiddenSyntax($meta['content'])) {
                $this->error("Refusing to save {$key}: forbidden syntax detected in generator.");

                return self::FAILURE;
            }

            $existing = Setting::get("email_template_{$key}_content");
            if ($existing && ! $this->option('force')) {
                $this->line("Skip {$key} (already has content; use --force to overwrite)");
                Setting::set("email_template_{$key}_custom", true, 'boolean', 'email_templates', "Custom enabled for {$key}");
                Setting::set("email_template_{$key}_subject", $meta['subject'], 'string', 'email_templates', "Subject for {$key}");
                continue;
            }

            Setting::set("email_template_{$key}_subject", $meta['subject'], 'string', 'email_templates', "Subject for {$key}");
            Setting::set("email_template_{$key}_content", $meta['content'], 'text', 'email_templates', "Content for {$key}");
            Setting::set("email_template_{$key}_custom", true, 'boolean', 'email_templates', "Custom enabled for {$key}");
            $written++;
            $this->info("Saved {$key}");
        }

        $this->info("Done. Wrote {$written} template(s). Custom templates are enabled.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{subject: string, content: string}>
     */
    private function templates(string $dashboardUrl, string $year): array
    {
        $wrap = fn (string $title, string $subtitle, string $bodyHtml): string => $this->layout($title, $subtitle, $bodyHtml, $dashboardUrl, $year);

        return [
            'business-verification' => [
                'subject' => 'Verify Your Email Address - {{ $appName }}',
                'content' => $wrap('Verify your email', 'Secure Payment Gateway', <<<'HTML'
<div style="font-size:18px;font-weight:600;color:#1a202c;margin-bottom:20px;">Hello {{ $business->name }}!</div>
<div style="font-size:15px;color:#4a5568;margin-bottom:20px;line-height:1.7;">Welcome to {{ $appName }}. Please verify your email to activate your account.</div>
<div style="background:linear-gradient(135deg,#f0f4ff 0%,#e8edff 100%);border:2px solid #3C50E0;border-radius:12px;padding:30px;margin:30px 0;text-align:center;">
  <div style="font-size:20px;font-weight:700;color:#1a202c;margin-bottom:8px;">{{ $business->name }}</div>
  <div style="font-size:14px;color:#718096;margin-bottom:20px;">{{ $business->email }}</div>
  <a href="{{ $verificationUrl }}" style="display:inline-block;background:linear-gradient(135deg,#3C50E0 0%,#2E40C7 100%);color:#ffffff!important;text-decoration:none;padding:16px 40px;border-radius:8px;font-weight:600;font-size:16px;">Verify Email Address</a>
  <div style="margin-top:20px;font-size:13px;color:#718096;">Or use this PIN: <strong style="letter-spacing:4px;color:#c53030;font-size:22px;">{{ $verificationPin }}</strong></div>
</div>
<div style="font-size:13px;color:#718096;word-break:break-all;">Link: {{ $verificationUrl }}</div>
<div style="background:#fff5f5;border-left:4px solid #fc8181;padding:15px 20px;margin:25px 0;border-radius:4px;font-size:13px;color:#742a2a;">This link and PIN expire in 60 minutes. If you did not sign up, ignore this email.</div>
HTML),
            ],
            'login-notification' => [
                'subject' => 'New Login Detected - {{ $appName }}',
                'content' => $wrap('New login detected', 'Account security alert', <<<'HTML'
<div style="font-size:18px;font-weight:600;color:#1a202c;margin-bottom:20px;">Hello {{ $business->name }}!</div>
<div style="font-size:15px;color:#4a5568;margin-bottom:20px;line-height:1.7;">We detected a new login to your {{ $appName }} account. If this was you, you can ignore this email.</div>
<div style="background:#f7fafc;padding:15px;border-radius:8px;border-left:4px solid #3C50E0;margin:12px 0;"><div style="font-size:12px;color:#718096;text-transform:uppercase;">IP address</div><div style="font-size:16px;font-weight:600;color:#1a202c;word-break:break-all;">{{ $ipAddress }}</div></div>
<div style="background:#f7fafc;padding:15px;border-radius:8px;border-left:4px solid #3C50E0;margin:12px 0;"><div style="font-size:12px;color:#718096;text-transform:uppercase;">Device</div><div style="font-size:14px;font-weight:600;color:#1a202c;word-break:break-all;">{{ $userAgent }}</div></div>
<div style="background:#fff7ed;border-left:4px solid #f59e0b;padding:15px 20px;margin:25px 0;border-radius:4px;font-size:13px;color:#92400e;"><strong>Security notice:</strong> If you did not log in, change your password immediately and contact support.</div>
HTML),
            ],
            'new-deposit' => [
                'subject' => 'Payment Received - {{ $appName }}',
                'content' => $wrap('Payment received', 'Funds credited to your balance', <<<'HTML'
<div style="font-size:18px;font-weight:600;color:#1a202c;margin-bottom:20px;">Hello {{ $business->name }}!</div>
<div style="font-size:15px;color:#4a5568;margin-bottom:20px;line-height:1.7;">A payment has been approved and credited to your {{ $appName }} account.</div>
<div style="background:linear-gradient(135deg,#e8edff 0%,#d6deff 100%);border:2px solid #3C50E0;border-radius:12px;padding:30px;margin:30px 0;text-align:center;">
  <div style="font-size:32px;font-weight:700;color:#1e293b;">NGN {{ $payment->amount }}</div>
  <div style="font-size:14px;color:#475569;margin-top:8px;">Payment received</div>
</div>
<div style="background:#f7fafc;padding:15px;border-radius:8px;border-left:4px solid #3C50E0;margin:12px 0;"><div style="font-size:12px;color:#718096;text-transform:uppercase;">Transaction ID</div><div style="font-size:16px;font-weight:600;color:#1a202c;">{{ $payment->transaction_id }}</div></div>
<div style="background:#f7fafc;padding:15px;border-radius:8px;border-left:4px solid #3C50E0;margin:12px 0;"><div style="font-size:12px;color:#718096;text-transform:uppercase;">From</div><div style="font-size:16px;font-weight:600;color:#1a202c;">{{ $payment->payer_name }}</div></div>
<div style="background:#f7fafc;padding:15px;border-radius:8px;border-left:4px solid #3C50E0;margin:12px 0;"><div style="font-size:12px;color:#718096;text-transform:uppercase;">Account number</div><div style="font-size:16px;font-weight:600;color:#1a202c;">{{ $payment->account_number }}</div></div>
<div style="font-size:15px;color:#4a5568;margin-top:20px;">View full details in your dashboard.</div>
HTML),
            ],
            'website-approved' => [
                'subject' => 'Website Approved - {{ $appName }}',
                'content' => $wrap('Website approved', 'Your site is ready for payments', <<<'HTML'
<div style="font-size:18px;font-weight:600;color:#1a202c;margin-bottom:20px;">Hello {{ $business->name }}!</div>
<div style="font-size:15px;color:#4a5568;margin-bottom:20px;line-height:1.7;">Good news — your website has been approved on {{ $appName }}.</div>
<div style="background:linear-gradient(135deg,#e8edff 0%,#d6deff 100%);border:2px solid #3C50E0;border-radius:12px;padding:30px;margin:30px 0;text-align:center;">
  <div style="font-size:18px;font-weight:700;color:#1e293b;word-break:break-all;">{{ $website->website_url }}</div>
</div>
<div style="font-size:15px;color:#4a5568;">You can now accept payments for this website from your dashboard.</div>
HTML),
            ],
            'website-added' => [
                'subject' => 'New Website Added - {{ $appName }}',
                'content' => $wrap('Website added', 'Pending review', <<<'HTML'
<div style="font-size:18px;font-weight:600;color:#1a202c;margin-bottom:20px;">Hello {{ $business->name }}!</div>
<div style="font-size:15px;color:#4a5568;margin-bottom:20px;line-height:1.7;">A new website was added to your {{ $appName }} portfolio and is awaiting approval.</div>
<div style="background:#f7fafc;padding:15px;border-radius:8px;border-left:4px solid #3C50E0;margin:12px 0;"><div style="font-size:12px;color:#718096;text-transform:uppercase;">Website</div><div style="font-size:16px;font-weight:600;color:#1a202c;word-break:break-all;">{{ $website->website_url }}</div></div>
<div style="font-size:15px;color:#4a5568;">We will email you when it is approved.</div>
HTML),
            ],
            'withdrawal-requested' => [
                'subject' => 'Withdrawal Request Submitted - {{ $appName }}',
                'content' => $wrap('Withdrawal requested', 'Pending review', <<<'HTML'
<div style="font-size:18px;font-weight:600;color:#1a202c;margin-bottom:20px;">Hello {{ $business->name }}!</div>
<div style="font-size:15px;color:#4a5568;margin-bottom:20px;line-height:1.7;">Your withdrawal request was submitted and is pending review.</div>
<div style="background:linear-gradient(135deg,#e8edff 0%,#d6deff 100%);border:2px solid #3C50E0;border-radius:12px;padding:30px;margin:30px 0;text-align:center;">
  <div style="font-size:32px;font-weight:700;color:#1e293b;">NGN {{ $withdrawal->amount }}</div>
  <div style="display:inline-block;background:#fef3c7;color:#78350f;padding:6px 16px;border-radius:20px;font-size:12px;font-weight:600;margin-top:12px;">Pending review</div>
</div>
<div style="background:#f7fafc;padding:15px;border-radius:8px;border-left:4px solid #3C50E0;margin:12px 0;"><div style="font-size:12px;color:#718096;text-transform:uppercase;">Bank</div><div style="font-size:16px;font-weight:600;color:#1a202c;">{{ $withdrawal->bank_name }}</div></div>
<div style="background:#f7fafc;padding:15px;border-radius:8px;border-left:4px solid #3C50E0;margin:12px 0;"><div style="font-size:12px;color:#718096;text-transform:uppercase;">Account name</div><div style="font-size:16px;font-weight:600;color:#1a202c;">{{ $withdrawal->account_name }}</div></div>
<div style="background:#f7fafc;padding:15px;border-radius:8px;border-left:4px solid #3C50E0;margin:12px 0;"><div style="font-size:12px;color:#718096;text-transform:uppercase;">Account number</div><div style="font-size:16px;font-weight:600;color:#1a202c;">{{ $withdrawal->account_number }}</div></div>
HTML),
            ],
            'withdrawal-approved' => [
                'subject' => 'Withdrawal Approved - {{ $appName }}',
                'content' => $wrap('Withdrawal approved', 'Payout in progress', <<<'HTML'
<div style="font-size:18px;font-weight:600;color:#1a202c;margin-bottom:20px;">Hello {{ $business->name }}!</div>
<div style="font-size:15px;color:#4a5568;margin-bottom:20px;line-height:1.7;">Your withdrawal has been approved on {{ $appName }}.</div>
<div style="background:linear-gradient(135deg,#e8edff 0%,#d6deff 100%);border:2px solid #3C50E0;border-radius:12px;padding:30px;margin:30px 0;text-align:center;">
  <div style="font-size:32px;font-weight:700;color:#1e293b;">NGN {{ $withdrawal->amount }}</div>
</div>
<div style="background:#f7fafc;padding:15px;border-radius:8px;border-left:4px solid #3C50E0;margin:12px 0;"><div style="font-size:12px;color:#718096;text-transform:uppercase;">Bank</div><div style="font-size:16px;font-weight:600;color:#1a202c;">{{ $withdrawal->bank_name }}</div></div>
<div style="background:#f7fafc;padding:15px;border-radius:8px;border-left:4px solid #3C50E0;margin:12px 0;"><div style="font-size:12px;color:#718096;text-transform:uppercase;">Account name</div><div style="font-size:16px;font-weight:600;color:#1a202c;">{{ $withdrawal->account_name }}</div></div>
<div style="background:#f7fafc;padding:15px;border-radius:8px;border-left:4px solid #3C50E0;margin:12px 0;"><div style="font-size:12px;color:#718096;text-transform:uppercase;">Account number</div><div style="font-size:16px;font-weight:600;color:#1a202c;">{{ $withdrawal->account_number }}</div></div>
<div style="font-size:15px;color:#4a5568;margin-top:16px;">Funds will arrive according to your bank’s processing times.</div>
HTML),
            ],
            'password-changed' => [
                'subject' => 'Password Changed - {{ $appName }}',
                'content' => $wrap('Password changed', 'Account security alert', <<<'HTML'
<div style="font-size:18px;font-weight:600;color:#1a202c;margin-bottom:20px;">Hello {{ $business->name }}!</div>
<div style="font-size:15px;color:#4a5568;margin-bottom:20px;line-height:1.7;">Your {{ $appName }} account password was changed.</div>
<div style="background:#f7fafc;padding:15px;border-radius:8px;border-left:4px solid #ef4444;margin:12px 0;"><div style="font-size:12px;color:#718096;text-transform:uppercase;">IP address</div><div style="font-size:16px;font-weight:600;color:#1a202c;word-break:break-all;">{{ $ipAddress }}</div></div>
<div style="background:#f7fafc;padding:15px;border-radius:8px;border-left:4px solid #ef4444;margin:12px 0;"><div style="font-size:12px;color:#718096;text-transform:uppercase;">Device</div><div style="font-size:14px;font-weight:600;color:#1a202c;word-break:break-all;">{{ $userAgent }}</div></div>
<div style="background:#fff7ed;border-left:4px solid #f59e0b;padding:15px 20px;margin:25px 0;border-radius:4px;font-size:13px;color:#92400e;"><strong>If this was not you:</strong> reset your password again and contact support immediately.</div>
HTML),
            ],
        ];
    }

    private function layout(string $title, string $subtitle, string $bodyHtml, string $dashboardUrl, string $year): string
    {
        $escapedDash = htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fa;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#333;">
  <div style="padding:20px;">
    <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.08);">
      <div style="background:linear-gradient(135deg,#3C50E0 0%,#2E40C7 100%);padding:36px 28px;text-align:center;">
        <div style="color:#ffffff;font-size:22px;font-weight:700;margin-bottom:6px;">{{ \$appName }}</div>
        <div style="color:rgba(255,255,255,0.9);font-size:14px;">{$subtitle}</div>
      </div>
      <div style="padding:36px 28px;">
        {$bodyHtml}
        <div style="text-align:center;margin-top:28px;">
          <a href="{$escapedDash}" style="display:inline-block;background:linear-gradient(135deg,#3C50E0 0%,#2E40C7 100%);color:#ffffff!important;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:15px;">Go to dashboard</a>
        </div>
      </div>
      <div style="background:#1a202c;padding:24px;text-align:center;color:#a0aec0;font-size:13px;">
        &copy; {$year} {{ \$appName }}. All rights reserved.
      </div>
    </div>
  </div>
</body>
</html>
HTML;
    }
}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Changed - {{ $appName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f5f7fa; line-height: 1.6; color: #333333; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        .email-header { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 40px 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .email-header h1 { color: #ffffff; font-size: 28px; font-weight: 700; margin-bottom: 10px; }
        .email-body { padding: 40px 30px; }
        .greeting { font-size: 18px; font-weight: 600; color: #1a202c; margin-bottom: 20px; }
        .content-text { font-size: 15px; color: #4a5568; margin-bottom: 20px; line-height: 1.7; }
        .security-box { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border: 2px solid #ef4444; border-radius: 12px; padding: 30px; margin: 30px 0; text-align: center; }
        .security-box .icon { width: 64px; height: 64px; background: #ef4444; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #ffffff; }
        .security-box .message { font-size: 18px; font-weight: 600; color: #991b1b; margin-bottom: 10px; }
        .info-grid { display: grid; gap: 15px; margin: 25px 0; }
        .info-item { background-color: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #ef4444; }
        .info-item .label { font-size: 12px; color: #718096; text-transform: uppercase; margin-bottom: 5px; }
        .info-item .value { font-size: 16px; font-weight: 600; color: #1a202c; word-break: break-all; }
        .warning-box { background-color: #fff7ed; border-left: 4px solid #f59e0b; padding: 15px 20px; margin: 25px 0; border-radius: 4px; }
        .warning-box .warning-text { font-size: 13px; color: #92400e; line-height: 1.6; }
        .email-footer { background-color: #1a202c; padding: 30px; text-align: center; border-radius: 0 0 8px 8px; }
        .email-footer .footer-text { color: #a0aec0; font-size: 13px; }
        @media only screen and (max-width: 600px) {
            .email-body { padding: 30px 20px; }
            .email-header { padding: 30px 20px; }
        }
    </style>
</head>
<body>
    <div style="padding: 20px;">
        <div class="email-container">
            <div class="email-header">
                @php
                    $emailLogo = \App\Models\Setting::get('email_logo');
                    $emailLogoPath = $emailLogo ? storage_path('app/public/' . $emailLogo) : null;
                @endphp
                @if($emailLogo && $emailLogoPath && file_exists($emailLogoPath))
                    <img src="{{ asset('storage/' . $emailLogo) }}" alt="{{ $appName }}" style="max-height: 50px; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto;">
                @else
                    <h1>{{ $appName }}</h1>
                @endif
                <div style="color: rgba(255, 255, 255, 0.9); font-size: 14px;">Password Changed</div>
            </div>
            <div class="email-body">
                <div class="greeting">Hello {{ $business->name }}!</div>
                <div class="content-text">This is a security notification to inform you that your password has been successfully changed.</div>
                <div class="security-box">
                    <div class="icon">🔒</div>
                    <div class="message">Password Changed Successfully</div>
                    <div style="color: #991b1b; font-size: 14px; margin-top: 10px;">{{ now()->format('F d, Y \a\t g:i A') }}</div>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">IP Address</div>
                        <div class="value">{{ $ipAddress }}</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Device/Browser</div>
                        <div class="value">{{ $userAgent }}</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Time</div>
                        <div class="value">{{ now()->format('F d, Y \a\t g:i A') }}</div>
                    </div>
                </div>
                <div class="warning-box">
                    <div class="warning-text">
                        <strong>⚠️ Security Notice:</strong> If you didn't make this change, please contact our support team immediately and change your password again. Your account security is our top priority.
                    </div>
                </div>
                <div class="content-text">If you made this change, you can safely ignore this email. For security reasons, we recommend using a strong, unique password.</div>
                <div style="text-align: center;">
                    <a href="{{ route('business.login') }}" style="display: inline-block; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: #ffffff !important; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 16px; margin: 20px 0;">Login to Account</a>
                </div>
            </div>
            <div class="email-footer">
                <div class="footer-text">© {{ date('Y') }} {{ $appName }}. All rights reserved.</div>
            </div>
        </div>
    </div>
</body>
</html>

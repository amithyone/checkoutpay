<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Approved - {{ $appName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f5f7fa; line-height: 1.6; color: #333333; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        .email-header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .email-header h1 { color: #ffffff; font-size: 28px; font-weight: 700; margin-bottom: 10px; }
        .email-body { padding: 40px 30px; }
        .greeting { font-size: 18px; font-weight: 600; color: #1a202c; margin-bottom: 20px; }
        .content-text { font-size: 15px; color: #4a5568; margin-bottom: 20px; line-height: 1.7; }
        .success-box { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border: 2px solid #10b981; border-radius: 12px; padding: 30px; margin: 30px 0; text-align: center; }
        .success-box .icon { width: 64px; height: 64px; background: #10b981; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #ffffff; }
        .success-box .website-url { font-size: 20px; font-weight: 700; color: #065f46; margin-bottom: 10px; word-break: break-all; }
        .info-grid { display: grid; gap: 15px; margin: 25px 0; }
        .info-item { background-color: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #10b981; }
        .info-item .label { font-size: 12px; color: #718096; text-transform: uppercase; margin-bottom: 5px; }
        .info-item .value { font-size: 16px; font-weight: 600; color: #1a202c; }
        .cta-button { display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff !important; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 16px; margin: 20px 0; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4); }
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
                <div style="color: rgba(255, 255, 255, 0.9); font-size: 14px;">Website Approved</div>
            </div>
            <div class="email-body">
                <div class="greeting">Hello {{ $business->name }}!</div>
                <div class="content-text">Great news! Your website has been approved and is now active on our payment gateway.</div>
                <div class="success-box">
                    <div class="icon">✓</div>
                    <div class="website-url">{{ $website->website_url }}</div>
                    <div style="color: #065f46; font-size: 14px; margin-top: 10px;">Status: Approved</div>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">Approved Date</div>
                        <div class="value">{{ $website->approved_at ? $website->approved_at->format('F d, Y \a\t g:i A') : 'N/A' }}</div>
                    </div>
                    @if($website->notes)
                    <div class="info-item">
                        <div class="label">Notes</div>
                        <div class="value" style="font-weight: 400; font-size: 14px;">{{ $website->notes }}</div>
                    </div>
                    @endif
                </div>
                <div class="content-text">You can now use this website to accept payments through our gateway. Generate your API keys and start integrating!</div>
                <div style="text-align: center;">
                    <a href="{{ route('business.dashboard') }}" class="cta-button">Go to Dashboard</a>
                </div>
            </div>
            <div class="email-footer">
                <div class="footer-text">© {{ date('Y') }} {{ $appName }}. All rights reserved.</div>
            </div>
        </div>
    </div>
</body>
</html>

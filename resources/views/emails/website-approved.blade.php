<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Approved - {{ $appName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f5f7fa; line-height: 1.6; color: #333333; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .email-header { background: linear-gradient(135deg, #3C50E0 0%, #2E40C7 100%); padding: 40px 30px; text-align: center; }
        .email-header .logo-container { margin-bottom: 15px; }
        .email-header .logo-container img { max-height: 50px; display: block; margin: 0 auto; }
        .email-header h1 { color: #ffffff; font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .email-header .subtitle { color: rgba(255, 255, 255, 0.9); font-size: 14px; }
        .email-body { padding: 40px 30px; }
        .greeting { font-size: 18px; font-weight: 600; color: #1a202c; margin-bottom: 20px; }
        .content-text { font-size: 15px; color: #4a5568; margin-bottom: 20px; line-height: 1.7; }
        .success-box { background: linear-gradient(135deg, #e8edff 0%, #d6deff 100%); border: 2px solid #3C50E0; border-radius: 12px; padding: 30px; margin: 30px 0; text-align: center; }
        .success-box .icon { width: 64px; height: 64px; background: #3C50E0; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #ffffff; }
        .success-box .website-url { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 10px; word-break: break-all; }
        .info-grid { display: grid; gap: 15px; margin: 25px 0; }
        .info-item { background-color: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #3C50E0; }
        .info-item .label { font-size: 12px; color: #718096; text-transform: uppercase; margin-bottom: 5px; }
        .info-item .value { font-size: 16px; font-weight: 600; color: #1a202c; }
        .cta-button { display: inline-block; background: linear-gradient(135deg, #3C50E0 0%, #2E40C7 100%); color: #ffffff !important; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 16px; margin: 20px 0; box-shadow: 0 4px 12px rgba(60, 80, 224, 0.4); }
        .email-footer { background-color: #1a202c; padding: 30px; text-align: center; }
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
                <div class="logo-container">
                    @php
                        $siteLogo = \App\Models\Setting::get('site_logo');
                        $siteLogoPath = $siteLogo ? storage_path('app/public/' . $siteLogo) : null;
                    @endphp
                    @if($siteLogo && $siteLogoPath && file_exists($siteLogoPath))
                        <img src="{{ asset('storage/' . $siteLogo) }}?v={{ time() }}" alt="{{ $appName }}" style="max-height: 50px; display: block; margin: 0 auto;">
                    @else
                        <div style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                            <span style="color: #ffffff; font-size: 24px;">✓</span>
                        </div>
                    @endif
                </div>
                <h1>{{ $appName }}</h1>
                <div class="subtitle">Website Approved</div>
            </div>
            <div class="email-body">
                <div class="greeting">Hello {{ $business->name }}!</div>
                <div class="content-text">Great news! Your website has been approved and is now active on our payment gateway.</div>
                <div class="success-box">
                    <div class="icon">✓</div>
                    <div class="website-url">{{ $website->website_url }}</div>
                    <div style="color: #475569; font-size: 14px; margin-top: 10px;">Status: Approved</div>
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

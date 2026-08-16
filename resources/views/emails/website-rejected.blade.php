<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website not approved - {{ $appName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f5f7fa; line-height: 1.6; color: #333333; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .email-header { background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%); padding: 40px 30px; text-align: center; }
        .email-header h1 { color: #ffffff; font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .email-header .subtitle { color: rgba(255, 255, 255, 0.9); font-size: 14px; }
        .email-body { padding: 40px 30px; }
        .greeting { font-size: 18px; font-weight: 600; color: #1a202c; margin-bottom: 20px; }
        .content-text { font-size: 15px; color: #4a5568; margin-bottom: 20px; line-height: 1.7; }
        .status-box { background: #fef2f2; border: 2px solid #fecaca; border-radius: 12px; padding: 24px; margin: 24px 0; text-align: center; }
        .website-url { font-size: 18px; font-weight: 700; color: #1e293b; word-break: break-all; }
        .note-box { background-color: #f7fafc; padding: 16px; border-radius: 8px; border-left: 4px solid #b91c1c; margin: 20px 0; }
        .note-box .label { font-size: 12px; color: #718096; text-transform: uppercase; margin-bottom: 6px; }
        .note-box .value { font-size: 15px; color: #1a202c; white-space: pre-wrap; }
        .cta-button { display: inline-block; background: #3C50E0; color: #ffffff !important; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 16px; margin: 16px 0; }
        .email-footer { background-color: #1a202c; padding: 30px; text-align: center; }
        .email-footer .footer-text { color: #a0aec0; font-size: 13px; }
    </style>
</head>
<body>
    <div style="padding: 20px;">
        <div class="email-container">
            <div class="email-header">
                <h1>{{ $appName }}</h1>
                <div class="subtitle">Website not approved</div>
            </div>
            <div class="email-body">
                <div class="greeting">Hello {{ $business->name }},</div>
                <div class="content-text">We reviewed the website you submitted and it was not approved.</div>
                <div class="status-box">
                    <div class="website-url">{{ $website->website_url }}</div>
                    <div style="color: #991b1b; font-size: 14px; margin-top: 10px;">Status: Rejected</div>
                </div>
                @if($website->notes)
                <div class="note-box">
                    <div class="label">Note from our team</div>
                    <div class="value">{{ $website->notes }}</div>
                </div>
                @endif
                <div class="content-text">You can update the site and add it again from your dashboard, or reply if you need help.</div>
                <div style="text-align: center;">
                    <a href="{{ route('business.websites.index') }}" class="cta-button">View my websites</a>
                </div>
            </div>
            <div class="email-footer">
                <div class="footer-text">© {{ date('Y') }} {{ $appName }}. All rights reserved.</div>
            </div>
        </div>
    </div>
</body>
</html>

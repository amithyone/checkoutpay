<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Website Added - {{ $appName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f5f7fa; line-height: 1.6; color: #333333; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        .email-header { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 40px 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .email-header h1 { color: #ffffff; font-size: 28px; font-weight: 700; margin-bottom: 10px; }
        .email-body { padding: 40px 30px; }
        .greeting { font-size: 18px; font-weight: 600; color: #1a202c; margin-bottom: 20px; }
        .content-text { font-size: 15px; color: #4a5568; margin-bottom: 20px; line-height: 1.7; }
        .info-box { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border: 2px solid #3b82f6; border-radius: 12px; padding: 30px; margin: 30px 0; text-align: center; }
        .info-box .icon { width: 64px; height: 64px; background: #3b82f6; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #ffffff; }
        .info-box .website-url { font-size: 20px; font-weight: 700; color: #1e40af; margin-bottom: 10px; word-break: break-all; }
        .status-badge { display: inline-block; background: #fbbf24; color: #78350f; padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 10px; }
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
                <h1>{{ $appName }}</h1>
                <div style="color: rgba(255, 255, 255, 0.9); font-size: 14px;">New Website Added</div>
            </div>
            <div class="email-body">
                <div class="greeting">Hello {{ $business->name }}!</div>
                <div class="content-text">A new website has been added to your business portfolio. It is currently pending admin approval.</div>
                <div class="info-box">
                    <div class="icon">🌐</div>
                    <div class="website-url">{{ $website->website_url }}</div>
                    <div class="status-badge">Pending Approval</div>
                </div>
                <div class="content-text">Once approved, you'll be able to use this website to accept payments. We'll notify you as soon as the review is complete.</div>
                <div style="text-align: center;">
                    <a href="{{ route('business.websites.index') }}" style="display: inline-block; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #ffffff !important; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 16px; margin: 20px 0;">View Websites</a>
                </div>
            </div>
            <div class="email-footer">
                <div class="footer-text">© {{ date('Y') }} {{ $appName }}. All rights reserved.</div>
            </div>
        </div>
    </div>
</body>
</html>

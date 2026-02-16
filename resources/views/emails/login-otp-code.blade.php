<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your login code</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f5f7fa; line-height: 1.6; color: #333; }
        .email-container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .email-header { background: linear-gradient(135deg, #3C50E0 0%, #2E40C7 100%); padding: 40px 30px; text-align: center; }
        .email-header h1 { color: #fff; font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .email-header .subtitle { color: rgba(255,255,255,0.9); font-size: 14px; }
        .email-body { padding: 40px 30px; }
        .greeting { font-size: 18px; font-weight: 600; color: #1a202c; margin-bottom: 20px; }
        .content-text { font-size: 15px; color: #4a5568; margin-bottom: 24px; line-height: 1.7; }
        .code-box { background: #1a202c; border-radius: 12px; padding: 28px 40px; text-align: center; margin: 28px 0; }
        .code-box .code-label { color: #a0aec0; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }
        .code-box .code { font-size: 36px; font-weight: 700; color: #fff; letter-spacing: 0.35em; font-variant-numeric: tabular-nums; }
        .code-box .expiry { color: #718096; font-size: 13px; margin-top: 14px; }
        .email-footer { background: #1a202c; padding: 30px; text-align: center; }
        .email-footer .footer-text { color: #a0aec0; font-size: 13px; }
    </style>
</head>
<body>
    <div style="padding: 20px;">
        <div class="email-container">
            <div class="email-header">
                <h1>Your login code</h1>
                <div class="subtitle">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</div>
            </div>
            <div class="email-body">
                <div class="greeting">Hello,</div>
                <div class="content-text">Use the code below to log in. It expires in {{ $ttlMinutes }} minutes.</div>
                <div class="code-box">
                    <div class="code-label">One-time code</div>
                    <div class="code">{{ $code }}</div>
                    <div class="expiry">Valid for {{ $ttlMinutes }} minutes</div>
                </div>
                <div class="content-text" style="margin-bottom: 0;">If you didn't request this code, you can ignore this email.</div>
            </div>
            <div class="email-footer">
                <div class="footer-text">© {{ date('Y') }} {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}. All rights reserved.</div>
            </div>
        </div>
    </div>
</body>
</html>

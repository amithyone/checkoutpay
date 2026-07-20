<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: system-ui, -apple-system, sans-serif; line-height: 1.55; color: #111; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p style="margin: 0 0 16px;">Hello {{ $recipientName }},</p>
    <h1 style="font-size: 20px; margin: 0 0 12px;">{{ $announcementTitle }}</h1>
    <div style="white-space: pre-wrap; margin: 0 0 24px;">{{ $announcementBody }}</div>
    <p style="margin: 0; color: #666; font-size: 13px;">— {{ config('app.name') }}</p>
    <p style="margin: 16px 0 0; color: #999; font-size: 12px;">You received this because you have an account with us. This message was sent by email only (not WhatsApp).</p>
</body>
</html>

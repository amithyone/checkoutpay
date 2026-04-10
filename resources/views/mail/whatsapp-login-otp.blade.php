<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5;">
    <p>Hello {{ $recipientName }},</p>
    <p>Link your WhatsApp number to your CheckoutNow rentals account using <strong>either</strong> option below (same {{ $ttlMinutes }}-minute window):</p>

    @if(!empty($magicLinkUrl))
        <p><strong>Fastest:</strong> tap this link on your phone (no code to type):</p>
        <p><a href="{{ $magicLinkUrl }}" style="word-break: break-all;">Confirm WhatsApp link</a></p>
    @endif

    <p><strong>Or</strong> reply in WhatsApp with this 6-digit code:</p>
    <p style="font-size: 24px; font-weight: bold; letter-spacing: 4px;">{{ $code }}</p>

    <p>This link and code expire in <strong>{{ $ttlMinutes }} minutes</strong>. If you did not request this, ignore this email.</p>
    <p>— {{ config('app.name') }}</p>
</body>
</html>

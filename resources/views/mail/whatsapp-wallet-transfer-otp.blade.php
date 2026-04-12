<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5;">
    <p>You started a transfer from your {{ $brandName }} WhatsApp wallet.</p>
    <p><strong>{{ $summaryLine }}</strong></p>

    <p><strong>Option 1 — Code in WhatsApp</strong><br>
    Reply in WhatsApp with this 6-digit code (do not type your wallet PIN in chat):</p>
    <p style="font-size: 24px; font-weight: bold; letter-spacing: 4px;">{{ $code }}</p>
    <p>This code expires in <strong>{{ $otpTtlMinutes }} minutes</strong>.</p>

    <p><strong>Option 2 — Confirm with wallet PIN (web only)</strong><br>
    Open this link on your phone and enter your 4-digit wallet PIN there. Do not type your wallet PIN in WhatsApp.</p>
    <p><a href="{{ $securePinUrl }}" style="word-break: break-all;">Confirm transfer securely</a></p>
    <p>The link expires in <strong>{{ $linkTtlMinutes }} minutes</strong>.</p>

    <p>If you did not start this transfer, ignore this email and check your wallet in WhatsApp.</p>
    <p>— {{ $brandName }}</p>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: system-ui, -apple-system, sans-serif; line-height: 1.55; color: #111; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p style="margin: 0 0 16px;">Hello {{ $recipientName }},</p>
    <h1 style="font-size: 20px; margin: 0 0 12px;">We now have a referral programme</h1>
    <p style="margin: 0 0 16px;">
        Invite friends to {{ $brandName }} and earn bonuses when they join and use their wallet.
    </p>
    <p style="margin: 0 0 8px; font-weight: 600;">Your referral code</p>
    <p style="margin: 0 0 24px; font-size: 28px; font-weight: 700; letter-spacing: 0.15em;">{{ $payCode }}</p>
    <p style="margin: 0 0 16px;">
        Open the {{ $brandName }} app, go to <strong>Profile</strong>, scroll down to <strong>Refer and Earn</strong> to see your code, share it, and track your rewards.
    </p>
    <p style="margin: 0; color: #666; font-size: 13px;">— {{ config('app.name') }}</p>
    <p style="margin: 16px 0 0; color: #999; font-size: 12px;">You received this because you have a wallet with us. This message was sent by email only (not WhatsApp).</p>
</body>
</html>

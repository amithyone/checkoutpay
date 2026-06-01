@php
    $isReset = ($mode ?? 'setup') === 'reset';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isReset ? 'PIN reset complete' : 'PIN saved' }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 28rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
    </style>
</head>
<body>
    @if($isReset)
        <h1>PIN reset complete</h1>
        <p>Your new wallet PIN is active. <strong>Return to WhatsApp</strong> to use your wallet.</p>
    @else
        <h1>PIN saved</h1>
        <p>Your wallet PIN is set. <strong>Return to WhatsApp</strong> — we sent the next step there (your send name).</p>
    @endif
    <p>You can close this page.</p>
</body>
</html>

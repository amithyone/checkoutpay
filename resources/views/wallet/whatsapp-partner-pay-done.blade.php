<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment successful</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 28rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        .ok { color: #0d6b36; font-weight: 600; }
    </style>
</head>
<body>
    <h1 class="ok">Payment successful</h1>
    <p>₦{{ number_format($amount, 2) }} was debited from your WhatsApp wallet.</p>
    @if($transaction_id !== '')
        <p class="muted">Reference: {{ $transaction_id }}</p>
    @endif
    <p class="muted">You can close this page and return to the app.</p>
</body>
</html>

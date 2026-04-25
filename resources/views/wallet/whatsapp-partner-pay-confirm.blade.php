<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm wallet payment</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 28rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        label { display: block; font-weight: 600; margin-top: 1rem; }
        input[type="password"] { width: 100%; padding: 0.5rem; font-size: 1.25rem; letter-spacing: 0.2em; margin-top: 0.25rem; box-sizing: border-box; }
        button { margin-top: 1rem; padding: 0.6rem 1.2rem; font-size: 1rem; cursor: pointer; }
        .err { color: #b00020; margin-top: 0.5rem; }
        .muted { color: #555; font-size: 0.9rem; margin-top: 1rem; }
        .box { background: #f6f7f9; padding: 1rem; border-radius: 8px; margin-top: 0.75rem; white-space: pre-wrap; word-break: break-word; }
    </style>
</head>
<body>
    <h1>Pay {{ $business_name }}</h1>
    <p><strong>Amount:</strong> ₦{{ number_format($amount, 2) }}</p>
    <p><strong>Reference:</strong> {{ $order_reference }}</p>
    @if($payer_name !== '')
        <p><strong>Payer:</strong> {{ $payer_name }}</p>
    @endif
    <p class="muted">You are paying for:</p>
    <div class="box">{{ $order_summary }}</div>
    <p class="muted">Enter your 4-digit WhatsApp wallet PIN below only on this page — do not send it in WhatsApp chat.</p>

    <form method="post" action="{{ url('/wallet/partner-pay/'.$token) }}">
        @csrf
        <label for="wallet_pin">Wallet PIN</label>
        <input id="wallet_pin" name="wallet_pin" type="password" inputmode="numeric" pattern="\d{4}" maxlength="4" autocomplete="one-time-code" required value="{{ old('wallet_pin') }}">
        @error('wallet_pin')
            <p class="err">{{ $message }}</p>
        @enderror
        <button type="submit">Confirm payment</button>
    </form>
</body>
</html>

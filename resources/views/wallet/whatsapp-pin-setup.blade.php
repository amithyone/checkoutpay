<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set wallet PIN</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 28rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        label { display: block; font-weight: 600; margin-top: 1rem; }
        input[type="password"] { width: 100%; padding: 0.5rem; font-size: 1.25rem; letter-spacing: 0.2em; margin-top: 0.25rem; box-sizing: border-box; }
        button { margin-top: 1rem; padding: 0.6rem 1.2rem; font-size: 1rem; cursor: pointer; }
        .err { color: #b00020; margin-top: 0.5rem; }
        .muted { color: #555; font-size: 0.9rem; margin-top: 1rem; }
    </style>
</head>
<body>
    <h1>Set WhatsApp wallet PIN</h1>
    <p class="muted">Choose a new 4-digit PIN and confirm it. Wallet PIN is only entered on this page — do not send it in WhatsApp.</p>

    <form method="post" action="{{ url('/wallet/whatsapp/set-pin/'.$token) }}">
        @csrf
        <label for="wallet_pin">New PIN</label>
        <input id="wallet_pin" name="wallet_pin" type="password" inputmode="numeric" pattern="\d{4}" maxlength="4" autocomplete="new-password" required value="{{ old('wallet_pin') }}">
        <label for="wallet_pin_confirmation">Confirm PIN</label>
        <input id="wallet_pin_confirmation" name="wallet_pin_confirmation" type="password" inputmode="numeric" pattern="\d{4}" maxlength="4" autocomplete="new-password" required value="{{ old('wallet_pin_confirmation') }}">
        @error('wallet_pin')
            <p class="err">{{ $message }}</p>
        @enderror
        <button type="submit">Save PIN</button>
    </form>
</body>
</html>

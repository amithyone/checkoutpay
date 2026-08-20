<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Quarantine</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; padding: 2rem; }
        .box { max-width: 36rem; margin: 3rem auto; background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1.75rem; }
        h1 { margin: 0 0 0.75rem; font-size: 1.35rem; color: #f87171; }
        p { line-height: 1.5; color: #cbd5e1; }
        ul { color: #94a3b8; }
        a { color: #93c5fd; }
        code { background: #0f172a; padding: 0.15rem 0.35rem; border-radius: 4px; font-size: 0.9em; }
    </style>
</head>
<body>
<div class="box">
    <h1>Site in quarantine</h1>
    <p>CheckoutPay locked itself because the database identity no longer looks safe (for example <code>DB_HOST</code> was changed). Do <strong>not</strong> run <code>migrate</code>.</p>
    @if(!empty($reasons))
        <p>Reason codes:</p>
        <ul>
            @foreach($reasons as $reason)
                <li><code>{{ $reason }}</code></li>
            @endforeach
        </ul>
    @endif
    <p><a href="{{ $statusUrl }}">Status</a> · <a href="{{ $unlockUrl }}">Unlock</a></p>
</div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unlock Quarantine</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; padding: 2rem; }
        .box { max-width: 36rem; margin: 3rem auto; background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1.75rem; }
        h1 { margin: 0 0 0.75rem; font-size: 1.35rem; }
        label { display: block; margin-bottom: 0.35rem; color: #cbd5e1; }
        input[type=password] { width: 100%; box-sizing: border-box; padding: 0.65rem; border-radius: 6px; border: 1px solid #475569; background: #0f172a; color: #f8fafc; }
        button { margin-top: 1rem; background: #3C50E0; color: #fff; border: 0; padding: 0.65rem 1.25rem; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .err { color: #f87171; margin-top: 0.75rem; }
        a { color: #93c5fd; }
        p { color: #94a3b8; line-height: 1.5; }
    </style>
</head>
<body>
<div class="box">
    <h1>Unlock quarantine</h1>
    <p>Fix <code>DB_HOST</code> / database name in <code>.error</code> first. Then enter the unlock code from your offline secrets. Alternatively delete <code>storage/framework/quarantine.lock</code> over SSH after fixing config.</p>
    <form method="post" action="{{ url('/quarantine/unlock') }}">
        @csrf
        <label for="code">Unlock code</label>
        <input id="code" type="password" name="code" required autocomplete="off" minlength="16">
        @error('code')
            <div class="err">{{ $message }}</div>
        @enderror
        <button type="submit">Clear quarantine</button>
    </form>
    <p style="margin-top:1.25rem"><a href="{{ url('/quarantine/status') }}">Back to status</a></p>
</div>
</body>
</html>

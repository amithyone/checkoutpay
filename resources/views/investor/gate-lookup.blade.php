<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout investor access</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Hanken Grotesk", system-ui, sans-serif; background: #f4f6fb; color: #0c1222;
            min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; margin: 0; }
        .card { width: min(420px, 100%); background: #fff; border: 1px solid rgba(12,18,34,.1); border-radius: 16px; padding: 1.5rem; }
        h1 { font-size: 1.35rem; margin: 0 0 .5rem; }
        p { color: #3a4558; font-size: .92rem; line-height: 1.5; }
        .flash { margin: .75rem 0; padding: .65rem .75rem; border-radius: 10px; font-size: .85rem; background: #fef2f2; color: #991b1b; }
        .ok { background: #ecfdf5; color: #065f46; }
        a { color: #001bca; font-weight: 700; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Personal investor link required</h1>
        <p>Each invitee receives a unique link and password bound to their name. Open the link we emailed or messaged you — it will greet you by name and ask for your password (which also accepts the NDA).</p>
        @if(session('error'))
            <div class="flash">{{ session('error') }}</div>
        @endif
        @if(session('success'))
            <div class="flash ok">{{ session('success') }}</div>
        @endif
        <p style="margin-top:1rem;font-size:.8rem;">If you lost your link, contact the Checkout team that invited you.</p>
    </div>
</body>
</html>

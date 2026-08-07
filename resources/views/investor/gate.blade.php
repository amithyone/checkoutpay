<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Hello {{ $access->name }} — Checkout investor access</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0c1222; --muted: #3a4558; --brand: #001bca; --line: rgba(12,18,34,.12);
            --paper: #f4f6fb; --font: "Hanken Grotesk", system-ui, sans-serif; --display: "Fraunces", Georgia, serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font); color: var(--ink); min-height: 100vh;
            background:
                radial-gradient(800px 400px at 10% -10%, rgba(0,27,202,.1), transparent 55%),
                linear-gradient(180deg, #f8f9fe, var(--paper));
            display: grid; place-items: center; padding: 1.5rem;
        }
        .card {
            width: min(440px, 100%);
            background: #fff; border: 1px solid var(--line); border-radius: 18px;
            padding: 1.75rem 1.5rem; box-shadow: 0 20px 50px rgba(12,18,34,.07);
        }
        .eyebrow { font-size: .7rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: var(--brand); }
        h1 { font-family: var(--display); font-size: 1.65rem; letter-spacing: -.02em; margin: .5rem 0 .65rem; line-height: 1.2; }
        p { color: var(--muted); font-size: .95rem; line-height: 1.5; }
        .hello { margin: 1rem 0 1.25rem; padding: 0.9rem 1rem; background: rgba(0,27,202,.05); border-radius: 12px; border: 1px solid rgba(0,27,202,.12); }
        .hello strong { color: var(--ink); }
        label { display: block; font-size: .8rem; font-weight: 700; margin: 0.9rem 0 .35rem; }
        input[type=password], input[type=text] {
            width: 100%; padding: .75rem .85rem; border: 1px solid var(--line); border-radius: 10px; font: inherit;
        }
        .nda {
            margin-top: 1rem; max-height: 140px; overflow: auto; font-size: .78rem; color: var(--muted);
            padding: .75rem; border: 1px solid var(--line); border-radius: 10px; background: #fafbfe;
        }
        .check { display: flex; gap: .55rem; align-items: flex-start; margin-top: .85rem; font-size: .85rem; color: var(--muted); }
        .check input { margin-top: .2rem; }
        .btn {
            margin-top: 1.1rem; width: 100%; border: 0; border-radius: 12px; padding: .85rem;
            background: var(--brand); color: #fff; font-weight: 800; font-size: .95rem; cursor: pointer;
        }
        .err { color: #b91c1c; font-size: .82rem; margin-top: .4rem; }
        .flash { margin-bottom: .85rem; padding: .7rem .85rem; border-radius: 10px; font-size: .85rem; }
        .flash.ok { background: #ecfdf5; color: #065f46; }
        .flash.bad { background: #fef2f2; color: #991b1b; }
        .meta { margin-top: 1rem; font-size: .75rem; color: #6b7280; text-align: center; }
    </style>
    @include('investor.partials.no-print')
</head>
<body>
    <div class="card">
        <p class="eyebrow">Checkout Now LTD · Confidential</p>
        <h1>Investor pitch access</h1>
        <p>This page and the materials behind it are confidential.</p>

        <div class="hello">
            Hello <strong>{{ $access->name }}</strong>@if($access->company), {{ $access->company }}@endif —
            here is your personal gate to view our investor pitch. Enter the password we shared with you to continue.
        </div>

        @if(session('success'))
            <div class="flash ok">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="flash bad">{{ session('error') }}</div>
        @endif

        <form method="post" action="{{ route('investor.gate.unlock', ['token' => $access->access_token]) }}">
            @csrf
            <label for="password">Your password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Password we sent you">
            @error('password') <p class="err">{{ $message }}</p> @enderror

            <div class="nda">
                <strong style="color:var(--ink);display:block;margin-bottom:.4rem;">Non-Disclosure Agreement (summary)</strong>
                By accessing the Checkout investor materials (including the pitch page and executive summary), you agree that:
                (1) all information is confidential and owned by Checkout Now LTD;
                (2) you will not copy, share, forward, publish, or discuss these materials with anyone outside your investment evaluation team without prior written consent;
                (3) you will use the information solely to evaluate a potential investment;
                (4) you will not reverse engineer products or solicit employees based on confidential disclosures herein;
                (5) obligations survive for three (3) years from access, or longer for trade secrets where permitted by law.
                Entering your password and checking the box below constitutes your electronic signature accepting this NDA.
            </div>

            <label class="check">
                <input type="checkbox" name="nda_accepted" value="1" @checked(old('nda_accepted')) required>
                <span>I am <strong style="color:var(--ink)">{{ $access->name }}</strong> (or authorised for this invite). I have read and accept the Non-Disclosure Agreement. Using this password constitutes my signature.</span>
            </label>
            @error('nda_accepted') <p class="err">{{ $message }}</p> @enderror

            <button class="btn" type="submit">Unlock investor pitch</button>
        </form>
        <p class="meta">Each password is bound to this invite. Do not share your link or password.</p>
    </div>
    @include('partials.session-keepalive')
</body>
</html>

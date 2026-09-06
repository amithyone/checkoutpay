<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --cn-bg: #0a0a0c;
        --cn-ink: #ffffff;
        --cn-muted: #9ca3af;
        --cn-blue: #3b82f6;
        --cn-blue-soft: #60a5fa;
        --cn-cyan: #22d3ee;
        --cn-purple: #a855f7;
        --cn-glass: rgba(255, 255, 255, 0.06);
        --cn-line: rgba(255, 255, 255, 0.1);
        --cn-safe: max(14px, env(safe-area-inset-bottom));
    }
    html, body { height: 100%; }
    body.pl-app {
        margin: 0;
        background: var(--cn-bg);
        color: var(--cn-ink);
        font-family: Manrope, ui-sans-serif, system-ui, sans-serif;
        overflow-x: hidden;
        -webkit-text-size-adjust: 100%;
    }
    .pl-atmosphere {
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 0;
        background: linear-gradient(180deg, #0a0a0c 0%, #0e0e12 50%, #0a0a0c 100%);
    }
    .pl-blob {
        position: absolute;
        border-radius: 9999px;
        filter: blur(70px);
        opacity: .95;
    }
    .pl-blob-a {
        width: 95vw; height: 95vw; max-width: 520px; max-height: 520px;
        top: -18%; left: -16%;
        background: radial-gradient(circle, rgba(37, 99, 235, 0.42), transparent 70%);
    }
    .pl-blob-b {
        width: 85vw; height: 85vw; max-width: 460px; max-height: 460px;
        bottom: -18%; right: -14%;
        background: radial-gradient(circle, rgba(147, 51, 234, 0.32), transparent 70%);
    }
    .pl-blob-c {
        width: 50vw; height: 50vw; max-width: 280px; max-height: 280px;
        bottom: 12%; left: 8%;
        background: radial-gradient(circle, rgba(88, 175, 255, 0.22), transparent 70%);
    }
    .pl-shell {
        position: relative;
        z-index: 1;
        min-height: 100dvh;
        max-width: 430px;
        margin: 0 auto;
        padding: 16px 20px calc(108px + var(--cn-safe));
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
    }
    .pl-brand {
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .32em;
        text-transform: uppercase;
        color: var(--cn-blue-soft);
        margin-bottom: 18px;
    }
    .pl-hero {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
    }
    .pl-avatar {
        width: 44px;
        height: 44px;
        border-radius: 9999px;
        background: rgba(59, 130, 246, .2);
        color: var(--cn-blue-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 14px;
        flex-shrink: 0;
    }
    .pl-hero h1 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: -.02em;
        line-height: 1.2;
    }
    .pl-hero p {
        margin: 3px 0 0;
        font-size: 12px;
        color: var(--cn-muted);
    }
    .pl-amount {
        text-align: center;
        padding: 10px 0 16px;
    }
    .pl-amount label {
        display: block;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .3em;
        text-transform: uppercase;
        color: var(--cn-blue);
        margin-bottom: 6px;
    }
    .pl-amount strong {
        display: block;
        font-size: 42px;
        font-weight: 200;
        letter-spacing: -.04em;
        line-height: 1;
    }
    .pl-note {
        margin: 8px 0 0;
        font-size: 12px;
        color: var(--cn-muted);
    }
    .pl-card {
        background: var(--cn-glass);
        border: 1px solid var(--cn-line);
        border-radius: 36px;
        padding: 20px 18px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, .35), inset 0 1px 0 rgba(255,255,255,.04);
        backdrop-filter: blur(18px);
    }
    .pl-kicker {
        margin: 0 0 10px;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .36em;
        text-transform: uppercase;
        color: var(--cn-cyan);
    }
    .pl-card h2 {
        margin: 0 0 6px;
        font-size: 15px;
        font-weight: 700;
    }
    .pl-hint {
        margin: 0 0 14px;
        font-size: 11px;
        color: var(--cn-muted);
        line-height: 1.4;
    }
    .pl-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .pl-row-main { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .pl-ico {
        width: 40px;
        height: 40px;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 15px;
    }
    .pl-ico.blue { background: rgba(59, 130, 246, .2); color: #60a5fa; }
    .pl-ico.purple { background: rgba(168, 85, 247, .2); color: #c084fc; }
    .pl-ico.cyan { background: rgba(6, 182, 212, .2); color: #22d3ee; }
    .pl-row p { margin: 0; font-size: 10px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: #6b7280; }
    .pl-row strong { display: block; margin-top: 2px; font-size: 14px; font-weight: 700; word-break: break-word; }
    .pl-row .pl-acc { font-size: 18px; letter-spacing: .04em; font-variant-numeric: tabular-nums; }
    .pl-copy {
        border: 0;
        background: transparent;
        color: #a855f7;
        padding: 8px;
        cursor: pointer;
        flex-shrink: 0;
    }
    .pl-rule { height: 1px; background: rgba(255,255,255,.06); margin: 14px 16px; }
    .pl-wait {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 16px;
        padding: 12px 12px;
        border-radius: 20px;
        background: rgba(245, 158, 11, .12);
        border: 1px solid rgba(245, 158, 11, .22);
    }
    .pl-wait-copy { min-width: 0; flex: 1; }
    .pl-wait-copy p { margin: 0; }
    .pl-wait-title { font-size: 13px; font-weight: 700; color: #fbbf24; }
    .pl-wait-sub { font-size: 11px; color: #d6b56a; margin-top: 2px; }
    .pl-btn {
        width: 100%;
        border: 0;
        border-radius: 9999px;
        padding: 15px 16px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .16em;
        text-transform: uppercase;
        cursor: pointer;
        text-align: center;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-sizing: border-box;
    }
    .pl-btn-primary {
        background: var(--cn-blue);
        color: #fff;
        box-shadow: 0 12px 28px rgba(59, 130, 246, .28);
        margin-top: 12px;
    }
    .pl-btn-primary:disabled { opacity: .55; cursor: wait; }
    .pl-btn-ghost {
        background: rgba(255,255,255,.05);
        color: var(--cn-ink);
        border: 1px solid var(--cn-line);
    }
    .pl-alt { margin-top: 14px; }
    .pl-alt summary {
        cursor: pointer;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: var(--cn-cyan);
        list-style: none;
        text-align: center;
    }
    .pl-alt summary::-webkit-details-marker { display: none; }
    .pl-form { display: grid; gap: 12px; margin-top: 12px; }
    .pl-field label { display: block; font-size: 10px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: #9ca3af; margin-bottom: 6px; }
    .pl-form input[type="text"],
    .pl-form input[type="email"],
    .pl-form input[type="number"] {
        width: 100%;
        box-sizing: border-box;
        border-radius: 18px;
        border: 1px solid var(--cn-line);
        background: rgba(255,255,255,.04);
        color: #fff;
        padding: 13px 14px;
        font-size: 15px;
        font-family: inherit;
    }
    .pl-methods {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .pl-method {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 6px;
        border: 1px solid var(--cn-line);
        border-radius: 26px;
        padding: 16px 10px 14px;
        background: rgba(255,255,255,.04);
        cursor: pointer;
        transition: border-color .15s ease, background .15s ease, box-shadow .15s ease, transform .15s ease;
    }
    .pl-method input {
        position: absolute;
        opacity: 0;
        width: 1px;
        height: 1px;
        margin: 0;
        pointer-events: none;
    }
    .pl-method-ico {
        width: 46px;
        height: 46px;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }
    .pl-method.is-transfer .pl-method-ico { background: rgba(20, 184, 166, .18); color: #2dd4bf; }
    .pl-method.is-card .pl-method-ico { background: rgba(249, 115, 22, .18); color: #fb923c; }
    .pl-method.is-transfer:has(:checked) {
        border-color: rgba(45, 212, 191, .75);
        background: rgba(20, 184, 166, .16);
        box-shadow: 0 0 0 1px rgba(45, 212, 191, .28), 0 14px 28px rgba(13, 148, 136, .22);
        transform: translateY(-1px);
    }
    .pl-method.is-card:has(:checked) {
        border-color: rgba(251, 146, 60, .8);
        background: rgba(249, 115, 22, .16);
        box-shadow: 0 0 0 1px rgba(251, 146, 60, .28), 0 14px 28px rgba(234, 88, 12, .22);
        transform: translateY(-1px);
    }
    .pl-method strong {
        font-size: 13px;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: #fff;
    }
    .pl-method > span:not(.pl-method-ico) {
        font-size: 11px;
        font-weight: 500;
        letter-spacing: 0;
        text-transform: none;
        color: var(--cn-muted);
    }
    .pl-btn.is-transfer {
        background: #14b8a6;
        box-shadow: 0 12px 28px rgba(20, 184, 166, .28);
    }
    .pl-btn.is-card {
        background: #ea580c;
        box-shadow: 0 12px 28px rgba(234, 88, 12, .28);
    }
    .pl-error {
        background: rgba(239, 68, 68, .12);
        color: #fecaca;
        border: 1px solid rgba(239, 68, 68, .28);
        border-radius: 18px;
        padding: 10px 12px;
        font-size: 13px;
        margin-bottom: 12px;
    }
    .pl-center { text-align: center; padding: 28px 12px; }
    .pl-center i { font-size: 36px; margin-bottom: 10px; }
    .pl-cta {
        position: fixed;
        left: 0; right: 0; bottom: 0;
        z-index: 20;
        padding: 10px 16px var(--cn-safe);
        background: linear-gradient(180deg, transparent, rgba(10,10,12,.92) 28%);
        backdrop-filter: blur(16px);
    }
    .pl-cta-inner {
        max-width: 430px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 12px;
        border: 1px solid var(--cn-line);
        background: var(--cn-glass);
        border-radius: 24px;
    }
    .pl-cta p { margin: 0; font-size: 11px; color: var(--cn-muted); line-height: 1.3; }
    .pl-cta strong { display: block; color: #fff; font-size: 12px; }
    .pl-cta a {
        flex-shrink: 0;
        background: var(--cn-blue);
        color: #fff;
        text-decoration: none;
        border-radius: 9999px;
        padding: 10px 12px;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        box-shadow: 0 8px 20px rgba(59, 130, 246, .28);
    }
    @keyframes pay-spin { to { transform: rotate(360deg); } }
    @keyframes pay-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .4; } }
    .pay-wait-spinner {
        width: 20px; height: 20px;
        border: 2px solid rgba(251, 191, 36, .25);
        border-top-color: #fbbf24;
        border-radius: 50%;
        animation: pay-spin .7s linear infinite;
        flex-shrink: 0;
    }
    .pay-wait-spinner.is-checking {
        border-color: rgba(96, 165, 250, .25);
        border-top-color: var(--cn-blue-soft);
        animation-duration: .45s;
    }
    .pay-wait-copy.is-checking { animation: pay-pulse 1s ease-in-out infinite; }
    .hidden { display: none !important; }
</style>

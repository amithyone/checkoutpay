<style>
    :root {
        --pl-primary: #001bca;
        --pl-primary-soft: #eef0ff;
        --pl-ink: #111827;
        --pl-muted: #64748b;
        --pl-line: #e8eaf2;
        --pl-bg: #f4f6fb;
        --pl-safe-bottom: max(12px, env(safe-area-inset-bottom));
    }
    html, body { height: 100%; }
    body.pl-app {
        margin: 0;
        background: var(--pl-bg);
        color: var(--pl-ink);
        font-family: "Hanken Grotesk", system-ui, sans-serif;
    }
    .pl-shell {
        min-height: 100dvh;
        max-width: 430px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        padding: 12px 16px calc(92px + var(--pl-safe-bottom));
        box-sizing: border-box;
    }
    @media (min-width: 768px) {
        body.pl-app { background: linear-gradient(180deg, #e8ecf8 0%, #f4f6fb 40%); }
        .pl-shell {
            min-height: 100dvh;
            padding-top: 20px;
        }
    }
    .pl-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    .pl-brand {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--pl-primary);
    }
    .pl-hero {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
    }
    .pl-avatar {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        background: var(--pl-primary);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 15px;
        flex-shrink: 0;
    }
    .pl-hero h1 {
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        line-height: 1.2;
    }
    .pl-hero p {
        margin: 2px 0 0;
        font-size: 13px;
        color: var(--pl-muted);
        line-height: 1.3;
    }
    .pl-amount {
        background: #fff;
        border: 1px solid var(--pl-line);
        border-radius: 18px;
        padding: 12px 14px;
        margin-bottom: 10px;
        box-shadow: 0 8px 24px rgba(17, 24, 39, .04);
    }
    .pl-amount label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: var(--pl-muted);
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: 2px;
    }
    .pl-amount strong {
        display: block;
        font-size: 28px;
        line-height: 1.1;
        font-weight: 800;
        letter-spacing: -.03em;
    }
    .pl-note {
        margin-top: 6px;
        font-size: 12px;
        color: #475569;
    }
    .pl-card {
        background: #fff;
        border: 1px solid var(--pl-line);
        border-radius: 18px;
        padding: 14px;
        box-shadow: 0 8px 24px rgba(17, 24, 39, .04);
    }
    .pl-card h2 {
        margin: 0 0 8px;
        font-size: 14px;
        font-weight: 700;
    }
    .pl-acc-num {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        background: var(--pl-primary-soft);
        border-radius: 14px;
        padding: 12px 12px;
        margin: 8px 0 10px;
    }
    .pl-acc-num span {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: .04em;
        word-break: break-all;
    }
    .pl-copy {
        border: 0;
        background: var(--pl-primary);
        color: #fff;
        border-radius: 10px;
        padding: 8px 10px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        flex-shrink: 0;
    }
    .pl-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        font-size: 12px;
    }
    .pl-meta p { margin: 0; color: var(--pl-muted); }
    .pl-meta strong { display: block; color: var(--pl-ink); font-size: 13px; margin-top: 2px; }
    .pl-wait {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 10px;
        padding: 10px;
        border-radius: 14px;
        background: #fff8e8;
        border: 1px solid #f3e0b0;
    }
    .pl-wait-copy { min-width: 0; flex: 1; }
    .pl-wait-copy p { margin: 0; }
    .pl-wait-title { font-size: 13px; font-weight: 700; color: #7a4d00; }
    .pl-wait-sub { font-size: 11px; color: #8a6320; margin-top: 2px; }
    .pl-check {
        width: 100%;
        margin-top: 8px;
        border: 0;
        background: var(--pl-primary);
        color: #fff;
        border-radius: 12px;
        padding: 11px 14px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
    }
    .pl-check:disabled { opacity: .65; cursor: wait; }
    .pl-alt { margin-top: 10px; }
    .pl-alt summary {
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        color: var(--pl-primary);
        list-style: none;
    }
    .pl-alt summary::-webkit-details-marker { display: none; }
    .pl-form { display: grid; gap: 10px; }
    .pl-form label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; }
    .pl-form input, .pl-form button {
        width: 100%;
        box-sizing: border-box;
        border-radius: 12px;
        font-size: 15px;
    }
    .pl-form input {
        border: 1px solid #d7dbe8;
        padding: 11px 12px;
        background: #fff;
    }
    .pl-methods {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .pl-method {
        display: flex;
        flex-direction: column;
        gap: 4px;
        border: 1px solid var(--pl-line);
        border-radius: 14px;
        padding: 10px;
        background: #fff;
        cursor: pointer;
    }
    .pl-method:has(:checked) {
        border-color: var(--pl-primary);
        background: var(--pl-primary-soft);
    }
    .pl-method strong { font-size: 13px; }
    .pl-method span { font-size: 11px; color: var(--pl-muted); }
    .pl-submit {
        border: 0;
        background: var(--pl-primary);
        color: #fff;
        border-radius: 12px;
        padding: 12px 14px;
        font-weight: 700;
        cursor: pointer;
    }
    .pl-error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
        border-radius: 12px;
        padding: 10px 12px;
        font-size: 13px;
        margin-bottom: 10px;
    }
    .pl-center { text-align: center; padding: 18px 8px; }
    .pl-center i { font-size: 34px; margin-bottom: 8px; }
    .pl-cta {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        background: #fff;
        border-top: 1px solid var(--pl-line);
        padding: 10px 16px var(--pl-safe-bottom);
        z-index: 20;
    }
    .pl-cta-inner {
        max-width: 430px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .pl-cta p { margin: 0; font-size: 12px; color: var(--pl-muted); line-height: 1.3; }
    .pl-cta strong { display: block; color: var(--pl-ink); font-size: 13px; }
    .pl-cta a {
        flex-shrink: 0;
        background: var(--pl-ink);
        color: #fff;
        text-decoration: none;
        border-radius: 11px;
        padding: 9px 12px;
        font-size: 12px;
        font-weight: 700;
    }
    @keyframes pay-spin { to { transform: rotate(360deg); } }
    @keyframes pay-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .45; } }
    .pay-wait-spinner {
        width: 22px;
        height: 22px;
        border: 3px solid #f3e0b0;
        border-top-color: #d97706;
        border-radius: 50%;
        animation: pay-spin 0.75s linear infinite;
        flex-shrink: 0;
    }
    .pay-wait-spinner.is-checking {
        border-color: #c7d2fe;
        border-top-color: var(--pl-primary);
        animation-duration: 0.5s;
    }
    .pay-wait-copy.is-checking { animation: pay-pulse 1s ease-in-out infinite; }
</style>

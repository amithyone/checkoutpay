<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Now — Executive Summary · Checkout Now LTD</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Executive summary for Checkout Now LTD: CheckoutPay, CheckoutNow, Cheko, Proximity Pay, and seed ask.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,500&family=Fraunces:opsz,wght@9..144,500;9..144,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0c1222;
            --ink-soft: #3a4558;
            --paper: #f4f6fb;
            --paper-2: #e8edf6;
            --line: rgba(12, 18, 34, 0.1);
            --brand: #001bca;
            --brand-mid: #2d3fe0;
            --sky: #8fd0ef;
            --radius: 18px;
            --max: 800px;
            --font: "Hanken Grotesk", system-ui, sans-serif;
            --display: "Fraunces", "Hanken Grotesk", Georgia, serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font);
            color: var(--ink);
            background:
                radial-gradient(900px 480px at 8% -8%, rgba(0, 27, 202, 0.08), transparent 55%),
                radial-gradient(700px 400px at 100% 0%, rgba(143, 208, 239, 0.3), transparent 50%),
                linear-gradient(180deg, #f8f9fe 0%, var(--paper) 50%, #eef2f9 100%);
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; }
        .wrap { width: min(var(--max), calc(100% - 2.5rem)); margin-inline: auto; }

        .topbar {
            position: sticky; top: 0; z-index: 40;
            backdrop-filter: blur(14px);
            background: rgba(244, 246, 251, 0.88);
            border-bottom: 1px solid var(--line);
        }
        .topbar-inner {
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; padding: 0.85rem 0;
            width: min(var(--max), calc(100% - 2.5rem)); margin-inline: auto;
        }
        .brand-mark {
            display: flex; align-items: baseline; gap: 0.55rem; text-decoration: none;
        }
        .brand-mark strong { font-size: 1.1rem; font-weight: 800; letter-spacing: -0.03em; }
        .brand-mark span {
            font-size: 0.7rem; font-weight: 600; letter-spacing: 0.08em;
            text-transform: uppercase; color: var(--ink-soft);
        }
        .nav-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
        .confidential {
            font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em;
            text-transform: uppercase; color: #b45309;
            border: 1px solid rgba(180, 83, 9, 0.35);
            background: rgba(180, 83, 9, 0.08);
            padding: 0.3rem 0.55rem; border-radius: 999px;
        }

        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 0.35rem; text-decoration: none; font-weight: 700;
            font-size: 0.88rem; padding: 0.7rem 1.1rem;
            border-radius: 12px; border: 1px solid transparent;
            transition: transform 0.2s ease, background 0.2s ease;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-ghost {
            background: rgba(0,27,202,0.05); color: var(--brand);
            border-color: rgba(0,27,202,0.2);
        }

        header.page-hero {
            padding: 2.75rem 0 1.5rem;
            border-bottom: 1px solid var(--line);
        }
        .eyebrow {
            font-size: 0.72rem; font-weight: 800; letter-spacing: 0.14em;
            text-transform: uppercase; color: var(--brand); margin-bottom: 0.65rem;
        }
        h1 {
            font-family: var(--display);
            font-size: clamp(2rem, 4.5vw, 2.75rem);
            letter-spacing: -0.03em; line-height: 1.12;
            margin-bottom: 0.75rem;
        }
        .lede { font-size: 1.05rem; color: var(--ink-soft); max-width: 38rem; }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.65rem;
            margin-top: 1.75rem;
        }
        @media (max-width: 640px) { .stats { grid-template-columns: 1fr 1fr; } }
        .stat {
            background: rgba(255,255,255,0.8);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 1rem 0.85rem;
        }
        .stat .n {
            font-family: var(--display);
            font-size: 1.45rem; font-weight: 700; color: var(--brand);
            letter-spacing: -0.02em;
        }
        .stat .l { font-size: 0.75rem; font-weight: 600; color: var(--ink-soft); margin-top: 0.2rem; }

        main { padding: 2rem 0 3.5rem; }
        article section { padding: 1.5rem 0; border-bottom: 1px solid var(--line); }
        article section:last-of-type { border-bottom: 0; }
        h2 {
            font-family: var(--display);
            font-size: 1.35rem;
            letter-spacing: -0.02em;
            margin-bottom: 0.65rem;
        }
        p, li { color: var(--ink-soft); font-size: 0.98rem; }
        p + p { margin-top: 0.75rem; }
        ul { padding-left: 1.15rem; display: grid; gap: 0.45rem; margin-top: 0.65rem; }
        strong { color: var(--ink); font-weight: 700; }

        .callout {
            margin-top: 0.9rem;
            padding: 1rem 1.1rem;
            border-left: 3px solid var(--brand);
            background: rgba(0,27,202,0.04);
            border-radius: 0 12px 12px 0;
            font-size: 0.95rem;
            color: var(--ink-soft);
        }

        .fund-row {
            display: grid; grid-template-columns: 10rem 1fr 2.5rem;
            gap: 0.65rem; align-items: center; margin: 0.55rem 0;
        }
        @media (max-width: 560px) {
            .fund-row { grid-template-columns: 1fr; gap: 0.2rem; }
        }
        .fund-label { font-size: 0.85rem; font-weight: 700; color: var(--ink); }
        .fund-track {
            height: 12px; border-radius: 999px; background: var(--paper-2); overflow: hidden;
        }
        .fund-fill { height: 100%; border-radius: 999px; }
        .fund-pct { font-weight: 800; font-size: 0.85rem; color: var(--brand); text-align: right; }

        .two {
            display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.85rem;
        }
        @media (max-width: 640px) { .two { grid-template-columns: 1fr; } }
        .card {
            background: rgba(255,255,255,0.75);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 1rem 1.05rem;
        }
        .card h3 { font-size: 0.95rem; font-weight: 800; margin-bottom: 0.4rem; color: var(--ink); }

        .cta-band {
            margin-top: 2rem;
            padding: 1.5rem;
            border-radius: var(--radius);
            background: linear-gradient(135deg, #0c1222, #0c1a4a 40%, #001bca);
            color: #fff;
        }
        .cta-band h2 { color: #fff; margin-bottom: 0.4rem; }
        .cta-band p { color: rgba(255,255,255,0.8); margin-bottom: 1rem; }
        .cta-band .btn-primary { background: #fff; color: var(--brand); }
        .cta-band .btn-ghost {
            background: rgba(255,255,255,0.1); color: #fff; border-color: rgba(255,255,255,0.3);
        }
        .cta-row { display: flex; flex-wrap: wrap; gap: 0.65rem; }

        footer {
            padding: 1.75rem 0 2.5rem;
            border-top: 1px solid var(--line);
            font-size: 0.82rem; color: var(--ink-soft);
        }
        footer strong { color: var(--ink); }

        @media print {
            /* Replaced by investor.partials.no-print */
        }
    </style>
    @include('investor.partials.no-print')
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand-mark" href="{{ route('investor.pitch') }}">
                <strong>Checkout Now</strong>
                <span>Exec summary</span>
            </a>
            <div class="nav-actions">
                @if(!empty($investorPitchViewer))
                    <span style="font-size:0.78rem;font-weight:600;color:#3a4558;">{{ $investorPitchViewer->name }}</span>
                    <form action="{{ route('investor.logout') }}" method="post" style="margin:0;">
                        @csrf
                        <button type="submit" class="btn btn-ghost" style="padding:0.4rem 0.75rem;font-size:0.75rem;">Sign out</button>
                    </form>
                @endif
                <a class="btn btn-ghost" href="{{ route('investor.pitch') }}" style="padding:0.45rem 0.85rem;font-size:0.8rem;">Full pitch</a>
                <div class="confidential">Confidential · NDA</div>
            </div>
        </div>
    </header>

    <header class="page-hero">
        <div class="wrap">
            <p class="eyebrow">Checkout Now LTD · Confidential</p>
            <h1>Executive summary</h1>
            <p class="lede">
                Checkout Now — dual-sided payments, Proximity Pay, and seed plans in one short brief.
            </p>
            <div class="stats">
                <div class="stat"><div class="n">{{ $metrics['volume'] }}</div><div class="l">Transaction volume</div></div>
                <div class="stat"><div class="n">{{ $metrics['daily'] }}</div><div class="l">Daily average</div></div>
                <div class="stat"><div class="n">80%+</div><div class="l">Core product shipped</div></div>
                <div class="stat"><div class="n">0</div><div class="l">Prior dilution / build debt</div></div>
            </div>
        </div>
    </header>

    <main>
        <article class="wrap">
            <section>
                <h2>The company</h2>
                <p>
                    <strong>Checkout Now LTD</strong> builds Nigeria’s dual-sided money stack:
                    <strong>CheckoutPay</strong> for merchants (collect, settle, grow),
                    <strong>CheckoutNow</strong> for consumers (wallet, pay, save, spend),
                    and <strong>Cheko</strong> Windows POS for the shop floor — with
                    <strong>Proximity Pay</strong> as the near-contactless in-store rail
                    (signed BLE broadcast; no card-tap hardware required).
                </p>
                <p>
                    Payments volume runs through licensed partners under SLA — notably
                    <strong>METRAVON INNOVATION LTD</strong>, which provides licensed rails.
                    Metavon is our <strong>licensed payments partner</strong>; Checkout Now LTD owns the products.
                </p>
                <div class="callout">
                    Merchants collect, consumers pay, shops settle with Proximity Pay — one ledger, bank-transfer-first.
                </div>
            </section>

            <section>
                <h2>The problem &amp; solution</h2>
                <p>
                    A large share of Nigerian commerce still runs on bank transfers and cash at the till.
                    Merchants need reliable collection without deploying card terminals everywhere;
                    consumers need a wallet that pays those merchants; in-shop transfer UX (typing account numbers) is slow.
                </p>
                <p>
                    Checkout owns both sides of that loop and closes it in-store with Cheko + Proximity Pay,
                    online with a WordPress / WooCommerce plugin, and in the browser with a business management web app.
                    We are also open-sourcing the <strong>Checkout Broadcast Protocol</strong> so other banks, wallets, and POS vendors can adopt the same proximity rail.
                </p>
            </section>

            <section>
                <h2>What is already live</h2>
                <ul>
                    <li><strong>Cheko</strong> — Windows POS ready (supermarket / hotel / retail)</li>
                    <li><strong>CheckoutNow</strong> — native apps on <strong>iOS and Android</strong> (App Store &amp; Google Play)</li>
                    <li><strong>Business web app</strong> — day-to-day merchant management</li>
                    <li><strong>WordPress plugin</strong> — CheckoutPay / COPN for WooCommerce</li>
                    <li>WhatsApp Wallet · Proximity Pay · open broadcast protocol</li>
                    <li><strong>80%+</strong> of the core development roadmap already shipped</li>
                </ul>
                <p style="margin-top:0.85rem;">
                    Next vertical: a <strong>dedicated rentals management app</strong> on the same Checkout backend —
                    toward an ecosystem that is too good to ignore (retail + payments today, rentals next).
                </p>
            </section>

            <section>
                <h2>Traction &amp; bootstrap</h2>
                <p>
                    <strong>{{ $metrics['volume'] }}</strong> cumulative transaction volume ·
                    <strong>{{ $metrics['daily'] }}</strong> daily average ·
                    <strong>{{ $metrics['merchants'] }}</strong> merchants ·
                    <strong>{{ $metrics['wallets'] }}</strong> wallet users.
                    Implied run-rate if daily holds: roughly <strong>{{ $metrics['runrate'] }}/year</strong>.
                </p>
                <p>
                    This stage was reached from the <strong>founder’s pocket</strong>:
                    <strong>no bank loans</strong>, <strong>no prior diluted share rounds</strong>, and
                    <strong>no build debt liabilities</strong>. Seed is the first outside capital raise into the business for growth.
                </p>
            </section>

            <section>
                <h2>Why lending works — and who we compete with</h2>
                <p>
                    Payment take-rates are thin. Combining <strong>payment + POS</strong> means we process the shop’s transactions,
                    so we see volume and can score who fits a business loan / overdraft program.
                    Lending (after <strong>FCCPC</strong> registration) is typically <strong>higher profit</strong> than regular payment fees alone.
                </p>
                <div class="two">
                    <div class="card">
                        <h3>vs RetailMan (POS)</h3>
                        <p>RetailMan-style tools sell POS, inventory, and staff management. They do not own the payment rail — so they do not sit on the cashflow that underwrites credit. We do.</p>
                    </div>
                    <div class="card">
                        <h3>vs Moniepoint (loans)</h3>
                        <p>Moniepoint and similar compete on MSME working capital. We layer credit on dual-sided Checkout volume + Cheko / Proximity Pay density — not a loan-only wedge.</p>
                    </div>
                </div>
                <div class="callout">
                    Payments get us into the shop. POS keeps us in the shop. Loans monetise trust from real volume.
                </div>
            </section>

            <section>
                <h2>Regulatory posture</h2>
                <p>
                    <strong>Today:</strong> payments under partner SLAs (CBN licenses sit with partners such as Metavon).
                    <strong>In progress:</strong> FCCPC digital lending registration — credit products are built and gated until issued.
                    <strong>Later:</strong> optional own CBN licenses (PSSP / MMO / PTSP) as Checkout Now scales.
                </p>
            </section>

            <section>
                <h2>The ask — how seed is used</h2>
                <p>
                    Seeking <strong>$750,000 – $1,500,000</strong> seed (SAFE or priced equity).
                    Capital funds competitive market access, contactless / Cheko density, compliance, and — after FCCPC — a controlled credit book.
                </p>
                @foreach ($funds as $f)
                    <div class="fund-row">
                        <div class="fund-label">{{ $f['label'] }}</div>
                        <div class="fund-track">
                            <div class="fund-fill" style="width: {{ $f['pct'] }}%; background: {{ $f['tone'] }};"></div>
                        </div>
                        <div class="fund-pct">{{ $f['pct'] }}%</div>
                    </div>
                @endforeach
                <p style="margin-top:0.75rem;font-size:0.85rem;">
                    * Credit liquidity after FCCPC registration.
                </p>
            </section>

            <div class="cta-band">
                <h2>Full pitch</h2>
                <p>Product flows, ecosystem map, loans thesis, and regulatory path.</p>
                <div class="cta-row">
                    <a class="btn btn-primary" href="{{ route('investor.pitch') }}">Open full pitch</a>
                    <a class="btn btn-ghost" href="{{ route('investor.pitch') }}#ask">The ask</a>
                </div>
            </div>
        </article>
    </main>

    <footer>
        <div class="wrap">
            <p><strong>Checkout Now LTD</strong> · CheckoutPay · CheckoutNow · Cheko</p>
            <p style="margin-top:0.3rem;">Payments SLA partner: METRAVON INNOVATION LTD</p>
            <p style="margin-top:0.65rem;font-size:0.75rem;">Confidential. Protected under NDA. Do not circulate.</p>
        </div>
    </footer>
</body>
</html>

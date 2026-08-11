<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Checkout Now — Investor Pitch · Checkout Now LTD</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Confidential investor overview: CheckoutPay, CheckoutNow, Cheko, and Proximity Pay.">
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
            --brand-soft: #4d61ff;
            --sky: #8fd0ef;
            --mint: #1f9d7a;
            --warn: #b45309;
            --radius: 18px;
            --max: 1120px;
            --font: "Hanken Grotesk", system-ui, sans-serif;
            --display: "Fraunces", "Hanken Grotesk", Georgia, serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font);
            color: var(--ink);
            background:
                radial-gradient(1200px 600px at 10% -10%, rgba(0, 27, 202, 0.08), transparent 55%),
                radial-gradient(900px 500px at 100% 8%, rgba(143, 208, 239, 0.35), transparent 50%),
                linear-gradient(180deg, #f8f9fe 0%, var(--paper) 40%, #eef2f9 100%);
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; }
        img { max-width: 100%; display: block; }

        .wrap { width: min(var(--max), calc(100% - 2.5rem)); margin-inline: auto; }

        .topbar {
            position: sticky; top: 0; z-index: 40;
            backdrop-filter: blur(14px);
            background: rgba(244, 246, 251, 0.82);
            border-bottom: 1px solid var(--line);
        }
        .topbar-inner {
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; padding: 0.85rem 0;
        }
        .brand-mark {
            display: flex; align-items: baseline; gap: 0.55rem;
            text-decoration: none;
        }
        .brand-mark strong {
            font-size: 1.15rem; font-weight: 800; letter-spacing: -0.03em;
        }
        .brand-mark span {
            font-size: 0.72rem; font-weight: 600; letter-spacing: 0.08em;
            text-transform: uppercase; color: var(--ink-soft);
        }
        .confidential {
            font-size: 0.7rem; font-weight: 700; letter-spacing: 0.06em;
            text-transform: uppercase; color: var(--warn);
            border: 1px solid rgba(180, 83, 9, 0.35);
            background: rgba(180, 83, 9, 0.08);
            padding: 0.35rem 0.65rem; border-radius: 999px;
        }
        .top-nav { display: flex; gap: 1rem; flex-wrap: wrap; }
        .top-nav a {
            font-size: 0.82rem; font-weight: 600; text-decoration: none;
            color: var(--ink-soft);
        }
        .top-nav a:hover { color: var(--brand); }

        /* ——— Hero ——— */
        .hero {
            position: relative;
            min-height: min(92vh, 860px);
            display: grid;
            align-items: end;
            overflow: hidden;
            border-bottom: 1px solid var(--line);
        }
        .hero-media {
            position: absolute; inset: 0;
            background:
                linear-gradient(160deg, rgba(0, 0, 0, 0.55) 0%, rgba(0, 0, 0, 0.45) 45%, rgba(0, 0, 0, 0.72) 100%),
                linear-gradient(45deg, #111111 0%, #1a1a1a 50%, #0a0a0a 100%);
        }
        .hero-media img {
            width: 100%; height: 100%; object-fit: cover;
            opacity: 0.72;
            filter: saturate(1.05) contrast(1.02);
            animation: heroDrift 28s ease-in-out infinite alternate;
        }
        .hero-media.empty {
            display: grid; place-items: center;
        }
        .hero-copy {
            position: relative; z-index: 2;
            padding: 4.5rem 0 3.5rem;
            color: #fff;
            max-width: 40rem;
            animation: riseIn 0.9s ease both;
        }
        .hero-copy .eyebrow {
            font-size: 0.75rem; font-weight: 700; letter-spacing: 0.14em;
            text-transform: uppercase; opacity: 0.85; margin-bottom: 1rem;
        }
        .hero-copy h1 {
            font-family: var(--display);
            font-size: clamp(2.6rem, 6vw, 4.4rem);
            font-weight: 700; letter-spacing: -0.03em;
            line-height: 1.05; margin-bottom: 1rem;
        }
        .hero-copy p {
            font-size: 1.1rem; max-width: 34rem;
            opacity: 0.92; margin-bottom: 1.75rem;
        }
        .cta-row { display: flex; flex-wrap: wrap; gap: 0.75rem; }
        .btn-exec {
            background: #8fd0ef;
            color: #0c1222;
        }
        .btn-exec:hover { background: #a8dbf3; }

        .exec {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: calc(var(--radius) + 4px);
            padding: 1.75rem 1.5rem 1.5rem;
            box-shadow: 0 18px 50px rgba(12, 18, 34, 0.06);
        }
        .exec-head {
            display: flex; flex-wrap: wrap; align-items: baseline;
            justify-content: space-between; gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .exec-head h2 { margin-bottom: 0; font-size: clamp(1.5rem, 3vw, 2rem); }
        .exec-badge {
            font-size: 0.72rem; font-weight: 800; letter-spacing: 0.08em;
            text-transform: uppercase; color: var(--brand);
            background: rgba(0,27,202,0.07);
            border: 1px solid rgba(0,27,202,0.15);
            padding: 0.35rem 0.65rem; border-radius: 999px;
        }
        .exec-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 1.25rem;
        }
        @media (max-width: 800px) {
            .exec-grid { grid-template-columns: 1fr; }
        }
        .exec dl {
            display: grid; gap: 0.85rem;
        }
        .exec dt {
            font-size: 0.7rem; font-weight: 800; letter-spacing: 0.1em;
            text-transform: uppercase; color: var(--brand); margin-bottom: 0.15rem;
        }
        .exec dd {
            font-size: 0.92rem; color: var(--ink-soft); line-height: 1.45;
        }
        .exec dd strong { color: var(--ink); font-weight: 700; }
        .exec-stats {
            display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem;
        }
        .exec-stat {
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 0.85rem 0.75rem;
        }
        .exec-stat .n {
            font-family: var(--display);
            font-size: 1.35rem; font-weight: 700; color: var(--brand);
            letter-spacing: -0.02em;
        }
        .exec-stat .l { font-size: 0.75rem; font-weight: 600; color: var(--ink-soft); margin-top: 0.15rem; }
        .exec-foot {
            margin-top: 1.25rem; padding-top: 1rem;
            border-top: 1px solid var(--line);
            display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: center;
            justify-content: space-between;
        }
        .exec-foot p { font-size: 0.82rem; color: var(--ink-soft); max-width: 36rem; }
        .exec-foot .cta-row { margin: 0; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 0.4rem; text-decoration: none; font-weight: 700;
            font-size: 0.92rem; padding: 0.85rem 1.25rem;
            border-radius: 12px; border: 1px solid transparent;
            transition: transform 0.2s ease, background 0.2s ease;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn-primary { background: #fff; color: var(--brand); }
        .btn-ghost { background: rgba(255,255,255,0.12); color: #fff; border-color: rgba(255,255,255,0.35); }

        /* ——— Photo slot ——— */
        .photo-slot {
            position: relative;
            border-radius: var(--radius);
            overflow: hidden;
            background:
                repeating-linear-gradient(-45deg, transparent, transparent 10px, rgba(0,27,202,0.03) 10px, rgba(0,27,202,0.03) 20px),
                var(--paper-2);
            border: 1.5px dashed rgba(0, 27, 202, 0.28);
            display: grid; place-items: center;
            min-height: 180px;
        }
        .photo-slot.has-image {
            border-style: solid; border-color: transparent;
            background: #0c1222;
        }
        .photo-slot img {
            width: 100%; height: 100%; object-fit: cover;
            position: absolute; inset: 0;
        }
        .photo-slot .placeholder {
            text-align: center; padding: 1.5rem; z-index: 1;
            color: var(--ink-soft);
        }
        .photo-slot .placeholder strong {
            display: block; color: var(--brand); font-size: 0.95rem;
            margin-bottom: 0.35rem;
        }
        .photo-slot .placeholder small {
            display: block; font-size: 0.78rem; max-width: 16rem; margin: 0.35rem auto 0;
        }
        .photo-slot .filename {
            margin-top: 0.75rem; font-family: ui-monospace, monospace;
            font-size: 0.72rem; color: var(--brand-mid);
            background: rgba(0,27,202,0.06); padding: 0.25rem 0.5rem; border-radius: 6px;
            display: inline-block;
        }
        .hero-media .placeholder { color: rgba(255,255,255,0.85); }
        .hero-media .placeholder strong { color: #fff; }
        .hero-media .filename { color: #fff; background: rgba(255,255,255,0.15); }

        /* ——— Sections ——— */
        section { padding: 4.5rem 0; }
        .section-label {
            font-size: 0.72rem; font-weight: 800; letter-spacing: 0.14em;
            text-transform: uppercase; color: var(--brand); margin-bottom: 0.75rem;
        }
        h2 {
            font-family: var(--display);
            font-size: clamp(1.85rem, 3.5vw, 2.65rem);
            letter-spacing: -0.025em; line-height: 1.15;
            margin-bottom: 0.75rem;
        }
        .lede { font-size: 1.08rem; color: var(--ink-soft); max-width: 40rem; }

        .grid-2 {
            display: grid; gap: 1.5rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 2rem;
        }
        .grid-3 {
            display: grid; gap: 1.25rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 2rem;
        }
        .grid-4 {
            display: grid; gap: 1rem;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-top: 2rem;
        }
        @media (max-width: 900px) {
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
            .grid-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
            .top-nav { display: none; }
        }

        .topbar-actions {
            display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
            justify-content: flex-end;
        }
        .mobile-nav {
            display: none;
            gap: 0.45rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            padding: 0 0 0.75rem;
            margin-top: -0.15rem;
        }
        .mobile-nav::-webkit-scrollbar { display: none; }
        .mobile-nav a {
            flex: 0 0 auto;
            font-size: 0.75rem;
            font-weight: 700;
            text-decoration: none;
            color: var(--ink-soft);
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 0.4rem 0.75rem;
            white-space: nowrap;
        }
        .mobile-nav a:hover { color: var(--brand); border-color: rgba(0,27,202,0.25); }

        .metric {
            background: rgba(255,255,255,0.72);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 1.35rem 1.25rem;
            position: relative; overflow: hidden;
            animation: riseIn 0.7s ease both;
            min-width: 0;
        }
        .metric:nth-child(2) { animation-delay: 0.08s; }
        .metric:nth-child(3) { animation-delay: 0.16s; }
        .metric:nth-child(4) { animation-delay: 0.24s; }
        .metric::after {
            content: ""; position: absolute; right: -20%; top: -30%;
            width: 140px; height: 140px; border-radius: 50%;
            background: radial-gradient(circle, rgba(0,27,202,0.1), transparent 70%);
        }
        .metric .value {
            font-family: var(--display);
            font-size: clamp(1.8rem, 3vw, 2.35rem);
            font-weight: 700; letter-spacing: -0.03em;
            color: var(--brand); line-height: 1.1;
        }
        .metric .label {
            margin-top: 0.35rem; font-size: 0.88rem; font-weight: 600; color: var(--ink-soft);
        }

        .panel {
            background: rgba(255,255,255,0.75);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 1.5rem;
        }
        .panel h3 {
            font-size: 1.05rem; font-weight: 800; margin-bottom: 0.5rem;
        }
        .panel p, .panel li { color: var(--ink-soft); font-size: 0.95rem; }
        .panel ul { padding-left: 1.1rem; display: grid; gap: 0.45rem; }

        /* Infographic: proximity loop */
        .flow {
            display: grid;
            grid-template-columns: 1fr auto 1fr auto 1fr auto 1fr;
            gap: 0.5rem; align-items: center;
            margin-top: 2rem;
        }
        @media (max-width: 800px) {
            .flow { grid-template-columns: 1fr; }
            .flow-arrow { transform: rotate(90deg); justify-self: center; }
        }
        .flow-node {
            background: #fff; border: 1px solid var(--line);
            border-radius: 14px; padding: 1rem; text-align: center;
            min-height: 110px; display: grid; place-content: center; gap: 0.35rem;
        }
        .flow-node .step {
            font-size: 0.68rem; font-weight: 800; letter-spacing: 0.1em;
            text-transform: uppercase; color: var(--brand);
        }
        .flow-node strong { font-size: 0.95rem; }
        .flow-node span { font-size: 0.78rem; color: var(--ink-soft); }
        .flow-arrow {
            width: 36px; height: 36px; color: var(--brand-mid); opacity: 0.7;
        }

        /* Vertical / channel flow diagrams */
        .diagram-block { margin-top: 2.25rem; }
        .diagram-block > h3 {
            font-size: 1.1rem; font-weight: 800; margin-bottom: 0.35rem;
        }
        .diagram-block > p {
            font-size: 0.9rem; color: var(--ink-soft); margin-bottom: 1rem; max-width: 40rem;
        }
        .vflow {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: stretch;
            justify-content: center;
        }
        .vflow .v-node {
            flex: 1 1 9.5rem;
            max-width: 11.5rem;
        }
        .vflow .v-arrow {
            flex: 0 0 auto;
            align-self: center;
        }
        @media (max-width: 900px) {
            .vflow .v-node { max-width: none; flex: 1 1 100%; }
            .vflow .v-arrow { transform: rotate(90deg); width: 100%; text-align: center; }
        }
        .v-node {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 0.9rem 0.75rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0.25rem;
            min-height: 96px;
        }
        .v-node.accent {
            background: linear-gradient(145deg, #0c1a4a, #001bca 60%, #2d3fe0);
            color: #fff;
            border-color: transparent;
        }
        .v-node.accent span { color: rgba(255,255,255,0.8); }
        .v-node .tag {
            font-size: 0.65rem; font-weight: 800; letter-spacing: 0.1em;
            text-transform: uppercase; color: var(--brand);
        }
        .v-node.accent .tag { color: #8fd0ef; }
        .v-node strong { font-size: 0.88rem; line-height: 1.25; }
        .v-node span { font-size: 0.72rem; color: var(--ink-soft); line-height: 1.3; }
        .v-arrow {
            display: grid; place-items: center;
            color: var(--brand-mid); opacity: 0.75;
            font-size: 1.25rem; font-weight: 700;
        }

        /* Ecosystem map */
        .eco-map {
            margin-top: 1.5rem;
            display: grid;
            grid-template-columns: 1fr 1.35fr 1fr;
            gap: 1rem;
            align-items: center;
        }
        @media (max-width: 900px) {
            .eco-map { grid-template-columns: 1fr; }
        }
        .eco-col { display: grid; gap: 0.75rem; }
        .eco-core {
            background: linear-gradient(145deg, #0c1a4a, #001bca 55%, #2d3fe0);
            color: #fff;
            border-radius: calc(var(--radius) + 4px);
            padding: 1.75rem 1.25rem;
            text-align: center;
            min-height: 280px;
            display: grid;
            place-content: center;
            gap: 0.55rem;
            position: relative;
            overflow: hidden;
            animation: pulseSoft 4s ease-in-out infinite;
        }
        .eco-core::before {
            content: "";
            position: absolute; inset: 18%;
            border: 1px dashed rgba(255,255,255,0.28);
            border-radius: 50%;
            pointer-events: none;
        }
        .eco-core .eyebrow {
            font-size: 0.68rem; letter-spacing: 0.14em; text-transform: uppercase;
            opacity: 0.8; position: relative;
        }
        .eco-core strong {
            font-family: var(--display);
            font-size: 1.55rem; position: relative;
        }
        .eco-core p {
            font-size: 0.85rem; opacity: 0.88; max-width: 16rem; margin: 0 auto; position: relative;
        }
        .eco-chip {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 0.95rem 1rem;
            position: relative;
        }
        .eco-chip::after {
            content: "↔";
            position: absolute;
            top: 50%;
            color: var(--brand-mid);
            font-weight: 800;
            opacity: 0.55;
            display: none;
        }
        @media (min-width: 901px) {
            .eco-col.left .eco-chip::after { display: block; right: -0.85rem; transform: translateY(-50%); }
            .eco-col.right .eco-chip::after { display: block; left: -0.85rem; transform: translateY(-50%) scaleX(-1); content: "↔"; }
        }
        .eco-chip .who {
            font-size: 0.68rem; font-weight: 800; letter-spacing: 0.1em;
            text-transform: uppercase; color: var(--brand); margin-bottom: 0.3rem;
        }
        .eco-chip strong { display: block; font-size: 0.95rem; margin-bottom: 0.2rem; }
        .eco-chip span { font-size: 0.78rem; color: var(--ink-soft); }
        .eco-loops {
            display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1.25rem; justify-content: center;
        }
        .eco-loop {
            font-size: 0.78rem; font-weight: 700;
            padding: 0.45rem 0.75rem;
            border-radius: 999px;
            background: rgba(0,27,202,0.07);
            border: 1px solid rgba(0,27,202,0.15);
            color: var(--brand);
        }

        /* Use of funds bars */
        .fund-row {
            display: grid; grid-template-columns: 11rem 1fr 3rem;
            gap: 0.75rem; align-items: center; margin: 0.65rem 0;
        }
        @media (max-width: 600px) {
            .fund-row { grid-template-columns: 1fr; gap: 0.25rem; }
        }
        .fund-label { font-size: 0.85rem; font-weight: 700; }
        .fund-track {
            height: 14px; border-radius: 999px; background: var(--paper-2);
            overflow: hidden;
        }
        .fund-fill {
            height: 100%; border-radius: 999px;
            transform-origin: left center;
            animation: barGrow 1.2s ease both;
        }
        .fund-pct { font-weight: 800; font-size: 0.9rem; color: var(--brand); text-align: right; }

        /* Protocol diagram */
        .protocol {
            display: grid; gap: 1rem;
            grid-template-columns: 1.1fr 1fr;
            margin-top: 2rem; align-items: stretch;
        }
        @media (max-width: 800px) { .protocol { grid-template-columns: 1fr; } }
        .hub {
            background: linear-gradient(145deg, #0c1a4a, #001bca 55%, #2d3fe0);
            color: #fff; border-radius: var(--radius); padding: 1.75rem;
            display: grid; place-content: center; text-align: center; gap: 0.5rem;
            min-height: 220px;
            animation: pulseSoft 4s ease-in-out infinite;
        }
        .hub strong { font-family: var(--display); font-size: 1.45rem; }
        .spokes {
            display: grid; gap: 0.75rem; grid-template-columns: 1fr 1fr;
        }
        .spoke {
            background: #fff; border: 1px solid var(--line);
            border-radius: 14px; padding: 1rem;
            font-size: 0.9rem; font-weight: 700;
        }
        .spoke span { display: block; font-weight: 500; font-size: 0.78rem; color: var(--ink-soft); margin-top: 0.25rem; }

        /* Volume chart (stylized) */
        .chart {
            display: flex; align-items: flex-end; gap: 0.55rem;
            height: 180px; margin-top: 1.5rem; padding-top: 0.5rem;
        }
        .bar {
            flex: 1; border-radius: 8px 8px 4px 4px;
            background: linear-gradient(180deg, var(--brand-soft), var(--brand));
            min-height: 12%;
            animation: barGrow 1s ease both;
            position: relative;
            opacity: 0.85;
        }
        .bar:nth-child(odd) { opacity: 1; }
        .bar .tip {
            position: absolute; top: -1.4rem; left: 50%; transform: translateX(-50%);
            font-size: 0.65rem; font-weight: 700; color: var(--ink-soft); white-space: nowrap;
        }
        .chart-note {
            margin-top: 0.75rem; font-size: 0.8rem; color: var(--ink-soft);
        }

        /* Timeline */
        .timeline {
            display: grid; gap: 0;
            margin-top: 2rem;
            border-left: 2px solid rgba(0,27,202,0.25);
            padding-left: 1.25rem;
        }
        .tl-item { padding: 0 0 1.5rem; position: relative; }
        .tl-item::before {
            content: ""; position: absolute; left: -1.55rem; top: 0.35rem;
            width: 10px; height: 10px; border-radius: 50%;
            background: var(--brand); box-shadow: 0 0 0 4px rgba(0,27,202,0.15);
        }
        .tl-item strong { display: block; margin-bottom: 0.2rem; }
        .tl-item span { font-size: 0.9rem; color: var(--ink-soft); }

        .win-list { display: grid; gap: 0.85rem; margin-top: 1.5rem; }
        .win {
            display: grid; grid-template-columns: auto 1fr; gap: 0.9rem;
            align-items: start; padding: 1rem 0;
            border-bottom: 1px solid var(--line);
        }
        .win:last-child { border-bottom: 0; }
        .win-num {
            font-family: var(--display); font-size: 1.4rem; font-weight: 700;
            color: var(--brand); line-height: 1;
        }

        .ask {
            position: relative; overflow: hidden;
            border-radius: calc(var(--radius) + 4px);
            background: linear-gradient(135deg, #0c1222, #0c1a4a 40%, #001bca);
            color: #fff; padding: 2.5rem 2rem;
        }
        .ask h2 { color: #fff; }
        .ask .lede { color: rgba(255,255,255,0.78); }
        .ask-grid {
            display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem; margin-top: 1.75rem;
        }
        @media (max-width: 700px) { .ask-grid { grid-template-columns: 1fr; } }
        .ask-card {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 14px; padding: 1.1rem;
        }
        .ask-card .k { font-size: 0.72rem; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.7; }
        .ask-card .v { font-size: 1.15rem; font-weight: 800; margin-top: 0.25rem; }

        footer.pitch-foot {
            padding: 2.5rem 0 3rem;
            border-top: 1px solid var(--line);
            color: var(--ink-soft); font-size: 0.85rem;
        }
        footer.pitch-foot strong { color: var(--ink); }

        .quote {
            margin-top: 1.5rem; padding: 1.25rem 1.35rem;
            border-left: 3px solid var(--brand);
            background: rgba(0,27,202,0.04);
            border-radius: 0 12px 12px 0;
            font-size: 0.95rem; color: var(--ink-soft); font-style: italic;
        }

        @keyframes riseIn {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes barGrow {
            from { transform: scaleX(0); }
            to { transform: scaleX(1); }
        }
        .bar { transform-origin: bottom center; animation-name: barRise; }
        @keyframes barRise {
            from { transform: scaleY(0); }
            to { transform: scaleY(1); }
        }
        @keyframes heroDrift {
            from { transform: scale(1.05) translate(0, 0); }
            to { transform: scale(1.12) translate(-1.5%, 1%); }
        }
        @keyframes pulseSoft {
            0%, 100% { filter: brightness(1); }
            50% { filter: brightness(1.08); }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation: none !important; transition: none !important;
            }
        }

        /* ——— Mobile / small screens ——— */
        @media (max-width: 720px) {
            .wrap { width: min(var(--max), calc(100% - 1.5rem)); }
            body { overflow-x: hidden; }

            .topbar-inner {
                flex-wrap: wrap;
                gap: 0.55rem;
                padding: 0.7rem 0 0.35rem;
            }
            .brand-mark strong { font-size: 1rem; }
            .brand-mark span { display: none; }
            .confidential {
                font-size: 0.62rem;
                padding: 0.28rem 0.5rem;
            }
            .topbar-actions {
                margin-left: auto;
                gap: 0.35rem;
            }
            .topbar-actions .viewer-name { display: none; }
            .mobile-nav { display: flex; }

            .hero {
                min-height: auto;
                align-items: end;
            }
            .hero-copy {
                padding: 3.25rem 0 2rem;
                max-width: none;
            }
            .hero-copy .eyebrow {
                font-size: 0.68rem;
                letter-spacing: 0.1em;
                margin-bottom: 0.75rem;
                line-height: 1.35;
            }
            .hero-copy h1 {
                font-size: clamp(2.1rem, 11vw, 2.75rem);
                margin-bottom: 0.75rem;
            }
            .hero-copy p {
                font-size: 0.98rem;
                margin-bottom: 1.25rem;
                line-height: 1.5;
            }
            .cta-row .btn {
                flex: 1 1 auto;
                min-width: min(100%, 9.5rem);
                padding: 0.8rem 1rem;
                font-size: 0.88rem;
            }

            section { padding: 2.75rem 0; }
            h2 {
                font-size: clamp(1.45rem, 6.5vw, 1.85rem);
                line-height: 1.2;
            }
            .lede { font-size: 0.98rem; }

            .metric { padding: 1rem 0.9rem; }
            .metric .value { font-size: clamp(1.35rem, 6vw, 1.75rem); }
            .metric .label { font-size: 0.78rem; line-height: 1.35; }

            .panel { padding: 1.15rem; }
            .panel p, .panel li { font-size: 0.9rem; }

            .flow-node { min-height: 0; padding: 0.9rem; }
            .ask { padding: 1.5rem 1.15rem; border-radius: var(--radius); }
            .ask-card .v { font-size: 1rem; line-height: 1.4; word-break: break-word; }

            .photo-slot { min-height: 160px; }
            .quote { padding: 1rem; font-size: 0.9rem; }

            .chart { min-height: 140px; }
            footer.pitch-foot { padding: 1.75rem 0 2.25rem; font-size: 0.8rem; }
        }

        @media (max-width: 420px) {
            .grid-4 { gap: 0.55rem; }
            .metric { padding: 0.85rem 0.7rem; }
            .metric .value { font-size: 1.25rem; }
            .cta-row .btn { width: 100%; min-width: 0; }
        }
    </style>
    @include('investor.partials.no-print')
</head>
<body>
    <header class="topbar">
        <div class="wrap topbar-inner">
            <a class="brand-mark" href="#top">
                <strong>Checkout Now</strong>
                <span>Investor</span>
            </a>
            <nav class="top-nav" aria-label="Sections">
                <a href="{{ route('investor.summary') }}">Exec summary</a>
                <a href="#flows">Flows</a>
                <a href="#ecosystem">Ecosystem</a>
                <a href="#loans">Loans</a>
                <a href="#ask">Seed use</a>
            </nav>
            <div class="topbar-actions">
                @if(!empty($investorPitchViewer))
                    <span class="viewer-name" style="font-size:0.75rem;font-weight:600;color:var(--ink-soft);">{{ $investorPitchViewer->name }}</span>
                    <form action="{{ route('investor.logout') }}" method="post" style="margin:0;">
                        @csrf
                        <button type="submit" style="font-size:0.7rem;font-weight:700;border:1px solid var(--line);background:#fff;border-radius:999px;padding:0.3rem 0.65rem;cursor:pointer;">Sign out</button>
                    </form>
                @endif
                <div class="confidential">Confidential · NDA</div>
            </div>
        </div>
        <nav class="wrap mobile-nav" aria-label="Sections (mobile)">
            <a href="{{ route('investor.summary') }}">Exec summary</a>
            <a href="#flows">Flows</a>
            <a href="#ecosystem">Ecosystem</a>
            <a href="#traction">Traction</a>
            <a href="#loans">Loans</a>
            <a href="#ask">Seed use</a>
        </nav>
    </header>

    <main id="top">
        {{-- HERO --}}
        <section class="hero" aria-label="Hero">
            <div class="hero-media {{ $photos['hero']['url'] ? '' : 'empty' }}">
                @if ($photos['hero']['url'])
                    <img src="{{ $photos['hero']['url'] }}" alt="Checkout Now">
                @endif
            </div>
            <div class="wrap hero-copy">
                <p class="eyebrow">Checkout Now LTD · CheckoutPay · CheckoutNow · Cheko</p>
                <h1>Checkout Now</h1>
                <p>Self-funded to {{ $metrics['volume'] }} volume and {{ $metrics['tx_count'] }} transactions in the {{ $metrics['tx_period'] }} — raising seed to win the market, harden trust &amp; security, and push contactless Proximity Pay.</p>
                <div class="cta-row">
                    <a class="btn btn-exec" href="{{ route('investor.summary') }}">Executive summary</a>
                    <a class="btn btn-primary" href="#intro">Explore</a>
                    <a class="btn btn-ghost" href="#ask">The ask</a>
                </div>
            </div>
        </section>

        {{-- INTRODUCTION --}}
        <section id="intro">
            <div class="wrap">
                <p class="section-label">Introduction</p>
                <h2>Invested to this stage. Live. Scaling.</h2>
                <p class="lede">
                    Checkout Now LTD is Nigeria’s dual-sided money company: <strong style="color:var(--ink);">CheckoutPay</strong> for merchants,
                    <strong style="color:var(--ink);">CheckoutNow</strong> for consumers, and <strong style="color:var(--ink);">Cheko</strong> for the shop floor —
                    with <strong style="color:var(--ink);">Proximity Pay</strong> as the in-store pay method (phone near the till, no account typing).
                </p>
                <div class="grid-3" style="margin-top:2rem;">
                    <div class="panel">
                        <h3>Where we are</h3>
                        <p>Over <strong style="color:var(--ink);">{{ $metrics['volume'] }}</strong> processed volume and nearly <strong style="color:var(--ink);">{{ $metrics['tx_count'] }}</strong> transactions in the {{ $metrics['tx_period'] }}, live apps on App Store &amp; Google Play, {{ $metrics['merchants'] }} merchants, {{ $metrics['wallets'] }} wallets — and more than <strong style="color:var(--ink);">80% of the core development roadmap already shipped</strong>.</p>
                    </div>
                    <div class="panel">
                        <h3>How we got here</h3>
                        <p>We got here by investing over <strong style="color:var(--ink);">{{ $invested['labor'] }}</strong> in timed product, engineering, and market-research labor, and over <strong style="color:var(--ink);">{{ $invested['build'] }}</strong> in development, licensing, and operating build cost — weighing competition and growth at every step. <strong style="color:var(--ink);">No bank loans. No outside equity.</strong> We reached this stage without debt liabilities, and we keep the same standards for verification, licensing, and security as we scale.</p>
                    </div>
                    <div class="panel">
                        <h3>Ownership today</h3>
                        <p>There have been <strong style="color:var(--ink);">no prior share sales to outside investors</strong> — ownership has not been sold down. Seed is the first capital raise into the business for growth.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- PROBLEM --}}
        <section id="problem" style="background: rgba(255,255,255,0.45); border-block: 1px solid var(--line);">
            <div class="wrap">
                <p class="section-label">The problem</p>
                <h2>Paying at a shop is still slower than it should be.</h2>
                <p class="lede">Nigeria moves a huge share of daily trade through transfers and cash at the till — but shoppers and merchants both feel the friction.</p>
                <div class="grid-3" style="margin-top:2rem;">
                    <div class="panel">
                        <h3>Merchants</h3>
                        <p>Need reliable collection and settlement without expensive card machines at every counter — plus tools to grow (invoices, payroll, team).</p>
                    </div>
                    <div class="panel">
                        <h3>Shoppers</h3>
                        <p>Need one wallet that pays those shops and covers daily money jobs: send money, pay bills, save, spend — not five disconnected apps.</p>
                    </div>
                    <div class="panel">
                        <h3>At the till</h3>
                        <p>Typing account numbers is slow. Card machines are costly to put on every counter. Nigeria needs a lighter path to <strong style="color:var(--ink);">pay in seconds</strong>.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- SOLUTION --}}
        <section id="solution">
            <div class="wrap">
                <p class="section-label">The solution</p>
                <h2>One system for shops and shoppers. Pay at the till without typing numbers.</h2>
                <p class="lede">Checkout owns the loop: merchant collects → shopper pays → in-shop Proximity Pay — on bank-transfer-first payment paths run by licensed partners. Live across desktop, mobile, web, and WordPress.</p>

                <div class="grid-2">
                    <div class="panel">
                        <h3>What we sell today</h3>
                        <ul>
                            <li><strong>CheckoutPay</strong> — merchant gateway, dashboard, API, payroll, collections</li>
                            <li><strong>CheckoutNow</strong> — native <strong>iOS &amp; Android</strong> wallet (+ WhatsApp Wallet)</li>
                            <li><strong>Business web app</strong> — full business management in the browser</li>
                            <li><strong>WordPress plugin</strong> — CheckoutPay / COPN for WooCommerce stores</li>
                            <li><strong>Cheko</strong> — <strong>ready on Windows</strong> POS for supermarket, hotel, retail</li>
                            <li><strong>Proximity Pay</strong> — phone near the till; signed Bluetooth session (no card-tap hardware)</li>
                            <li><strong>Checkout Broadcast Protocol</strong> — open pay standard for banks, wallets, and POS</li>
                        </ul>
                        <p style="margin-top:1rem;font-size:0.85rem;color:var(--ink-soft);">
                            <strong style="color:var(--ink);">Checkout Now LTD</strong> owns these products.
                            Money moves through licensed payment partners — notably <strong style="color:var(--ink);">METRAVON INNOVATION LTD</strong> — under service agreements.
                        </p>
                    </div>
                    @include('investor.partials.photo-slot', ['slot' => $photos['retail'], 'aspect' => '16 / 10'])
                </div>

                <p class="section-label" style="margin-top:2.5rem;">Product ready</p>
                <h2 style="font-size:clamp(1.4rem,2.5vw,1.85rem);">Shipped across every surface that matters.</h2>
                <div class="grid-4" style="margin-top:1.25rem;">
                    <div class="metric">
                        <div class="value" style="font-size:1.35rem;">Cheko</div>
                        <div class="label">Windows POS — ready</div>
                    </div>
                    <div class="metric">
                        <div class="value" style="font-size:1.35rem;">iOS + Android</div>
                        <div class="label">CheckoutNow mobile apps</div>
                    </div>
                    <div class="metric">
                        <div class="value" style="font-size:1.35rem;">Web app</div>
                        <div class="label">Business management</div>
                    </div>
                    <div class="metric">
                        <div class="value" style="font-size:1.35rem;">WordPress</div>
                        <div class="label">WooCommerce plugin</div>
                    </div>
                </div>
            </div>
        </section>

        {{-- FLOW DIAGRAMS --}}
        <section id="flows">
            <div class="wrap">
                <p class="section-label">How it works</p>
                <h2>Flows for business, shoppers, and online stores.</h2>
                <p class="lede">Three entry points — same Checkout money system underneath. Each path feeds volume, stickiness, and (later) who can borrow.</p>

                <div class="diagram-block">
                    <h3>1 · Business + Cheko (Windows POS)</h3>
                    <p>Shop floor: ring up sale → send Proximity Pay request → customer pays → settle to business.</p>
                    <div class="vflow" role="img" aria-label="Cheko business flow">
                        <div class="v-node">
                            <div class="tag">01</div>
                            <strong>Open Cheko</strong>
                            <span>Windows till · inventory · staff</span>
                        </div>
                        <div class="v-arrow" aria-hidden="true">→</div>
                        <div class="v-node">
                            <div class="tag">02</div>
                            <strong>Ring up sale</strong>
                            <span>Cart · total · pay at shop</span>
                        </div>
                        <div class="v-arrow" aria-hidden="true">→</div>
                        <div class="v-node accent">
                            <div class="tag">03</div>
                            <strong>Bluetooth pay request</strong>
                            <span>Signed Proximity Pay session</span>
                        </div>
                        <div class="v-arrow" aria-hidden="true">→</div>
                        <div class="v-node">
                            <div class="tag">04</div>
                            <strong>Customer pays</strong>
                            <span>CheckoutNow phone nearby</span>
                        </div>
                        <div class="v-arrow" aria-hidden="true">→</div>
                        <div class="v-node">
                            <div class="tag">05</div>
                            <strong>Settle</strong>
                            <span>CheckoutPay → banks / VA</span>
                        </div>
                    </div>
                </div>

                <div class="diagram-block">
                    <h3>2 · User + CheckoutNow (iOS / Android)</h3>
                    <p>Consumer wallet: fund → pay shops / send money / bills → optional pay at till via Proximity Pay.</p>
                    <div class="vflow" role="img" aria-label="CheckoutNow mobile flow">
                        <div class="v-node">
                            <div class="tag">01</div>
                            <strong>Install app</strong>
                            <span>iOS · Android · identity checks</span>
                        </div>
                        <div class="v-arrow" aria-hidden="true">→</div>
                        <div class="v-node">
                            <div class="tag">02</div>
                            <strong>Fund wallet</strong>
                            <span>Bank transfer · VA top-up</span>
                        </div>
                        <div class="v-arrow" aria-hidden="true">→</div>
                        <div class="v-node accent">
                            <div class="tag">03</div>
                            <strong>Spend</strong>
                            <span>Send money · bills · merchant pay</span>
                        </div>
                        <div class="v-arrow" aria-hidden="true">→</div>
                        <div class="v-node">
                            <div class="tag">04</div>
                            <strong>Pay at shop</strong>
                            <span>Phone near Cheko · confirm pay</span>
                        </div>
                        <div class="v-arrow" aria-hidden="true">→</div>
                        <div class="v-node">
                            <div class="tag">05</div>
                            <strong>Save / card</strong>
                            <span>Savings · USD virtual card</span>
                        </div>
                    </div>
                </div>

                <div class="diagram-block">
                    <h3>3 · Business + WordPress plugin</h3>
                    <p>Online store: install plugin → customer checks out → CheckoutPay collects → webhook / settle to merchant.</p>
                    <div class="vflow" role="img" aria-label="WordPress plugin flow">
                        <div class="v-node">
                            <div class="tag">01</div>
                            <strong>Install plugin</strong>
                            <span>WooCommerce · COPN</span>
                        </div>
                        <div class="v-arrow" aria-hidden="true">→</div>
                        <div class="v-node">
                            <div class="tag">02</div>
                            <strong>Connect keys</strong>
                            <span>API · Business ID · webhooks</span>
                        </div>
                        <div class="v-arrow" aria-hidden="true">→</div>
                        <div class="v-node accent">
                            <div class="tag">03</div>
                            <strong>Customer pays</strong>
                            <span>Hosted / bank-transfer checkout</span>
                        </div>
                        <div class="v-arrow" aria-hidden="true">→</div>
                        <div class="v-node">
                            <div class="tag">04</div>
                            <strong>CheckoutPay</strong>
                            <span>Match · approve · notify store</span>
                        </div>
                        <div class="v-arrow" aria-hidden="true">→</div>
                        <div class="v-node">
                            <div class="tag">05</div>
                            <strong>Business dashboard</strong>
                            <span>Balance · withdraw · reports</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ECOSYSTEM / RENTALS --}}
        <section id="ecosystem" style="background: rgba(255,255,255,0.45); border-block: 1px solid var(--line);">
            <div class="wrap">
                <p class="section-label">Ecosystem expansion</p>
                <h2>Too good to ignore — one dynamic system.</h2>
                <p class="lede">
                    Every surface talks to the same core. More shops and wallets mean more volume; more volume means better loan fit and a stack that compounds.
                </p>

                <div class="eco-map" role="img" aria-label="Checkout ecosystem diagram">
                    <div class="eco-col left">
                        <div class="eco-chip">
                            <div class="who">Business · shop</div>
                            <strong>Cheko Windows</strong>
                            <span>POS · inventory · staff · Proximity Pay</span>
                        </div>
                        <div class="eco-chip">
                            <div class="who">Business · online</div>
                            <strong>WordPress plugin</strong>
                            <span>WooCommerce checkout → CheckoutPay</span>
                        </div>
                        <div class="eco-chip">
                            <div class="who">Business · ops</div>
                            <strong>Web dashboard</strong>
                            <span>Balance · payroll · invoices · team</span>
                        </div>
                    </div>
                    <div class="eco-core">
                        <div class="eyebrow">Checkout Now LTD</div>
                        <strong>One money system</strong>
                        <p>Payments · wallets · POS data · (soon) rentals — licensed partners move the money underneath</p>
                    </div>
                    <div class="eco-col right">
                        <div class="eco-chip">
                            <div class="who">Consumer</div>
                            <strong>CheckoutNow mobile</strong>
                            <span>iOS · Android · send money · bills · pay at shop</span>
                        </div>
                        <div class="eco-chip">
                            <div class="who">Consumer</div>
                            <strong>WhatsApp Wallet</strong>
                            <span>Low-friction onboarding in chat</span>
                        </div>
                        <div class="eco-chip">
                            <div class="who">Next vertical</div>
                            <strong>Rentals app</strong>
                            <span>Dedicated management · same money layer</span>
                        </div>
                    </div>
                </div>

                <div class="eco-loops" aria-label="Feedback loops">
                    <span class="eco-loop">Till ↔ phone (Proximity Pay)</span>
                    <span class="eco-loop">Store ↔ CheckoutPay (WordPress)</span>
                    <span class="eco-loop">Volume → who can borrow</span>
                    <span class="eco-loop">Retail today → rentals next</span>
                    <span class="eco-loop">Open pay standard → more wallets / POS</span>
                </div>

                <div class="grid-2" style="margin-top:2rem;">
                    <div class="panel">
                        <h3>Live today</h3>
                        <ul>
                            <li><strong>Cheko</strong> Windows POS — ready for shops</li>
                            <li><strong>CheckoutNow</strong> on App Store &amp; Google Play</li>
                            <li><strong>Business web app</strong> for day-to-day management</li>
                            <li><strong>WordPress / WooCommerce plugin</strong> for online merchants</li>
                            <li>WhatsApp Wallet · Proximity Pay · open pay standard</li>
                        </ul>
                    </div>
                    <div class="panel">
                        <h3>Next — rentals</h3>
                        <ul>
                            <li>We are expanding into <strong>rentals</strong> (inventory, bookings, payouts).</li>
                            <li>Building a <strong>dedicated rentals management app</strong> on the same Checkout Now backend.</li>
                            <li>Same idea: own the vertical’s operations + payments → sales history → stickiness → who can borrow later.</li>
                            <li>An ecosystem of tools so businesses and consumers stay inside Checkout Now because leaving means losing the stack.</li>
                        </ul>
                    </div>
                </div>
                <div class="quote" style="margin-top:1.5rem;">
                    Retail + payments + contactless today. Rentals management next. One ecosystem that compounds — too good to ignore.
                </div>
            </div>
        </section>

        {{-- TRACTION --}}
        <section id="traction" style="background: rgba(255,255,255,0.45); border-block: 1px solid var(--line);">
            <div class="wrap">
                <p class="section-label">What we have done</p>
                <h2>{{ $metrics['tx_count'] }} transactions in the {{ $metrics['tx_period'] }} — self-funded.</h2>
                <p class="lede">Real throughput on a live dual-sided product: {{ $metrics['volume'] }} cumulative volume. Built without loans and without selling shares to earlier investors.</p>

                <div class="grid-4">
                    <div class="metric">
                        <div class="value">{{ $metrics['tx_count'] }}</div>
                        <div class="label">Transactions · {{ $metrics['tx_period'] }}</div>
                    </div>
                    <div class="metric">
                        <div class="value">{{ $metrics['volume'] }}</div>
                        <div class="label">Transaction volume</div>
                    </div>
                    <div class="metric">
                        <div class="value">{{ $metrics['daily'] }}</div>
                        <div class="label">Daily average</div>
                    </div>
                    <div class="metric">
                        <div class="value">0</div>
                        <div class="label">Debt / prior share sales</div>
                    </div>
                </div>

                <div class="grid-2" style="margin-top: 2rem;">
                    <div class="panel">
                        <h3>Invested capital to this stage</h3>
                        <ul>
                            <li>Over <strong>{{ $invested['labor'] }}</strong> in timed product, engineering, and market-research labor</li>
                            <li>Over <strong>{{ $invested['build'] }}</strong> in development, licensing, tooling, and operating build cost</li>
                            <li><strong>No bank loans</strong> · <strong>no outside equity</strong> · <strong>no build debt</strong> on the balance sheet</li>
                            <li>Standards kept for verification, licensing, and security — the same discipline we scale with</li>
                            <li>Consumer apps <strong>live</strong> on App Store &amp; Google Play</li>
                            <li><strong>Cheko Windows</strong> ready · WordPress plugin · business web app</li>
                            <li>{{ $metrics['merchants'] }} merchants · {{ $metrics['wallets'] }} wallet users · {{ $metrics['tx_count'] }} txns in the {{ $metrics['tx_period'] }} · rentals expansion underway</li>
                        </ul>
                    </div>
                    <div class="panel">
                        <h3>Growth</h3>
                        <p style="margin-bottom: 0.5rem;">{{ $metrics['tx_count'] }} transactions in the {{ $metrics['tx_period'] }} · volume toward {{ $metrics['volume'] }} lifetime.</p>
                        <div class="chart" aria-hidden="true">
                            @foreach ([18, 28, 35, 42, 55, 68, 78, 92] as $i => $h)
                                <div class="bar" style="height: {{ $h }}%; animation-delay: {{ $i * 0.06 }}s">
                                    @if ($i === 7)
                                        <span class="tip">Now</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <p class="chart-note">Also live: WhatsApp Wallet · open Checkout Broadcast Protocol · Proximity Pay / Cheko.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- PRODUCT / PROXIMITY --}}
        <section id="product">
            <div class="wrap">
                <p class="section-label">Contactless push</p>
                <h2>Proximity Pay — pay at the till without typing account numbers.</h2>
                <p class="lede">The till sends a signed pay request over Bluetooth. The customer’s phone checks it and pays into merchant settlement. Seed rolls this pay method out across more shops — and opens it to other banks and wallets via our open pay standard.</p>

                <div class="flow" role="img" aria-label="Proximity Pay payment flow">
                    <div class="flow-node">
                        <div class="step">01</div>
                        <strong>Cheko / till</strong>
                        <span>Signed Bluetooth request</span>
                    </div>
                    <svg class="flow-arrow" viewBox="0 0 36 36" fill="none" aria-hidden="true"><path d="M8 18h18M20 10l8 8-8 8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <div class="flow-node">
                        <div class="step">02</div>
                        <strong>Phone</strong>
                        <span>CheckoutNow verifies</span>
                    </div>
                    <svg class="flow-arrow" viewBox="0 0 36 36" fill="none" aria-hidden="true"><path d="M8 18h18M20 10l8 8-8 8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <div class="flow-node">
                        <div class="step">03</div>
                        <strong>Pay</strong>
                        <span>Wallet → merchant</span>
                    </div>
                    <svg class="flow-arrow" viewBox="0 0 36 36" fill="none" aria-hidden="true"><path d="M8 18h18M20 10l8 8-8 8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <div class="flow-node">
                        <div class="step">04</div>
                        <strong>Settle</strong>
                        <span>Banks · VA / transfer</span>
                    </div>
                </div>

                <div class="panel" style="margin-top: 2rem;">
                    <h3>Trust &amp; security — what users feel</h3>
                    <ul>
                        <li><strong>Shoppers:</strong> no account numbers to type; the phone only pays after the request is checked</li>
                        <li><strong>Merchants:</strong> a signed till session they can trust; money settles through licensed partners</li>
                        <li><strong>Banks &amp; wallets:</strong> same open pay standard — verify before money moves</li>
                        <li><strong>Built-in checks:</strong> signed sessions, short-lived requests (harder to replay), identity and fraud ops as we scale</li>
                    </ul>
                </div>

                <div class="grid-2" style="margin-top: 2rem;">
                    @include('investor.partials.photo-slot', ['slot' => $photos['product_pay'], 'aspect' => '4 / 3'])
                    @include('investor.partials.photo-slot', ['slot' => $photos['product_cheko'], 'aspect' => '4 / 3'])
                </div>
            </div>
        </section>

        {{-- PROTOCOL --}}
        <section id="protocol" style="background: rgba(255,255,255,0.45); border-block: 1px solid var(--line);">
            <div class="wrap">
                <p class="section-label">Open pay standard</p>
                <h2>Checkout Broadcast Protocol</h2>
                <p class="lede">We are open-sourcing this pay standard so banks, wallets, and POS vendors can adopt the same in-store pay method — shared contactless pay without each bank rebuilding Bluetooth from scratch. Pioneer the category, stay interoperable.</p>

                <div class="protocol">
                    <div class="hub">
                        <div style="font-size:0.72rem;letter-spacing:0.12em;text-transform:uppercase;opacity:0.8;">Open standard</div>
                        <strong>Checkout Broadcast Protocol</strong>
                        <span style="opacity:0.85;font-size:0.9rem;">OSS · banks · wallets · POS</span>
                    </div>
                    <div class="spokes">
                        <div class="spoke">Cheko POS<span>First till implementation</span></div>
                        <div class="spoke">CheckoutNow<span>First wallet that pays</span></div>
                        <div class="spoke">Other POS<span>Vendors can implement</span></div>
                        <div class="spoke">Banks &amp; wallets<span>More phones → more tills</span></div>
                    </div>
                </div>
            </div>
        </section>

        {{-- WHY WIN --}}
        <section>
            <div class="wrap">
                <p class="section-label">Why we win</p>
                <h2>One system for shops and shoppers + an open in-store pay standard.</h2>
                <div class="win-list">
                    <div class="win"><div class="win-num">01</div><div><strong>One system for both sides</strong><p style="color:var(--ink-soft);margin-top:0.25rem;">Merchant collect and shopper spend share infrastructure; Proximity Pay closes the loop in-store.</p></div></div>
                    <div class="win"><div class="win-num">02</div><div><strong>Bank-transfer-first Nigeria fit</strong><p style="color:var(--ink-soft);margin-top:0.25rem;">Aligns with how Nigerians already pay; less dependency on card networks for core checkout.</p></div></div>
                    <div class="win"><div class="win-num">03</div><div><strong>Distribution stack</strong><p style="color:var(--ink-soft);margin-top:0.25rem;">Dashboard + WooCommerce + WhatsApp + native apps + Cheko POS.</p></div></div>
                    <div class="win"><div class="win-num">04</div><div><strong>Open Proximity Pay standard</strong><p style="color:var(--ink-soft);margin-top:0.25rem;">Banks and wallets can adopt the same pay method — category pioneer, not a walled garden.</p></div></div>
                    <div class="win"><div class="win-num">05</div><div><strong>Credit as the high-margin next product (licensed)</strong><p style="color:var(--ink-soft);margin-top:0.25rem;">Overdraft/loan after Nigeria’s digital lending license (FCCPC) — sticky product on observed shop volume.</p></div></div>
                </div>
            </div>
        </section>

        {{-- BUSINESS LOANS --}}
        <section id="loans" style="background: rgba(255,255,255,0.45); border-block: 1px solid var(--line);">
            <div class="wrap">
                <p class="section-label">Business loans</p>
                <h2>Payment + POS → who deserves credit.</h2>
                <p class="lede">
                    Regular payment fees are thin. Lending to merchants who already settle on Checkout is a higher-margin next product —
                    gated until Nigeria’s <strong style="color:var(--ink);">digital lending license (FCCPC)</strong> —
                    decided from real shop sales history, not guesswork.
                </p>

                <div class="grid-2" style="margin-top:2rem;">
                    <div class="panel">
                        <h3>Why lending works for us</h3>
                        <ul>
                            <li><strong>We see the cashflow.</strong> Combining CheckoutPay (payments) with Cheko (POS) means we process the shop’s transactions — volume, consistency, and settlement behaviour are visible.</li>
                            <li><strong>Fit for the loan program.</strong> Merchants who run payments + POS on Checkout naturally surface as eligible (or not) for overdraft / business loans — volume tiers already exist in product.</li>
                            <li><strong>Better economics.</strong> Interest / facility fees on lending typically out-earn thin pay-in / pay-out fees on the same merchant.</li>
                            <li><strong>Sticky loop.</strong> Credit keeps the merchant paying and settling through Checkout; more transactions improve who we can lend to next.</li>
                            <li><strong>Merchant benefit.</strong> One stack for till + payments + (later) capital — not a separate POS vendor and a separate lender.</li>
                        </ul>
                    </div>
                    <div class="panel">
                        <h3>Why we are unique vs POS-only</h3>
                        <p style="margin-bottom:0.75rem;">
                            Players like <strong style="color:var(--ink);">RetailMan</strong> focus on POS software: point of sale, inventory, and staff management.
                            They do not run the payments — so they do not see the cashflow that proves who can repay a loan.
                        </p>
                        <p>
                            <strong style="color:var(--ink);">Checkout combines payment + POS.</strong> The business gets a clear benefit (one system to sell and get paid).
                            We get access to process their transactions — then we know who fits the loan program.
                        </p>
                    </div>
                </div>

                <div class="grid-3" style="margin-top:1.5rem;">
                    <div class="panel">
                        <h3>Loan competitors</h3>
                        <p><strong style="color:var(--ink);">Moniepoint</strong> (and similar agent / merchant lenders) compete on business loans and working capital for small businesses. We layer credit on dual-sided Checkout volume + more Cheko / Proximity Pay shops live — not a loan-only product.</p>
                    </div>
                    <div class="panel">
                        <h3>POS competitors</h3>
                        <p><strong style="color:var(--ink);">RetailMan</strong> and classic POS suites sell inventory / staff / till software. We are not “just POS” — payments are native, so the till also shows the sales history that decides credit.</p>
                    </div>
                    <div class="panel">
                        <h3>Payments competitors</h3>
                        <p><strong style="color:var(--ink);">Paystack / Flutterwave</strong>-class collectors do well online but do not close the in-shop contactless loop with our Cheko + Proximity Pay stack the same way.</p>
                    </div>
                </div>

                <div class="quote" style="margin-top:1.75rem;">
                    Payments get us into the shop. POS keeps us in the shop. Loans monetise trust earned from real volume — at higher profit than regular payment fees alone.
                </div>
            </div>
        </section>

        {{-- TEAM PHOTO --}}
        <section style="padding-top:0;">
            <div class="wrap">
                @include('investor.partials.photo-slot', ['slot' => $photos['team'], 'aspect' => '21 / 9'])
            </div>
        </section>

        {{-- REGULATORY --}}
        <section id="path" style="background: rgba(255,255,255,0.45); border-block: 1px solid var(--line);">
            <div class="wrap">
                <p class="section-label">Regulatory path</p>
                <h2>Payments under partners. Lending gated on FCCPC.</h2>
                <div class="timeline">
                    <div class="tl-item">
                        <strong>Today — Licensed payment partners</strong>
                        <span>Merchant &amp; wallet volume via licensed payment partners under service agreements — notably <strong>METRAVON INNOVATION LTD</strong> (Central Bank–licensed partners handle the actual transfers; Checkout Now LTD does not hold that license itself today).</span>
                    </div>
                    <div class="tl-item">
                        <strong>In progress — Nigeria’s digital lending license (FCCPC)</strong>
                        <span>Overdraft / loan / peer lending built and held back until registration issues.</span>
                    </div>
                    <div class="tl-item">
                        <strong>Optional — Own Central Bank licenses later</strong>
                        <span>Processor (PSSP), own e-money (MMO), or terminal estate (PTSP) as Checkout Now scales independently.</span>
                    </div>
                </div>
                <blockquote class="quote">
                    “Checkout Now LTD operates CheckoutPay, CheckoutNow, and Cheko. We process merchant and wallet volume today through licensed payment partners under service agreements — including METRAVON INNOVATION LTD. Consumer and merchant credit products are built and held back pending Nigeria’s digital lending license (FCCPC), which is underway. Proceeds will fund more Cheko shops live, compliance capital, and — post-approval — a controlled credit book.”
                </blockquote>
            </div>
        </section>

        {{-- ASK / HOW SEED IS USED --}}
        <section id="ask">
            <div class="wrap">
                <div class="ask">
                    @if ($photos['ask_bg']['url'])
                        <img src="{{ $photos['ask_bg']['url'] }}" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0.25;mix-blend-mode:luminosity;pointer-events:none;">
                    @endif
                    <div style="position:relative;z-index:1;">
                        <p class="section-label" style="color:#8fd0ef;">Seed investment</p>
                        <h2>How investor money is used</h2>
                        <p class="lede">
                            Seed funds <strong style="color:#fff;">competitive scale and trust</strong> — more shops live, contactless Proximity Pay, security &amp; compliance —
                            not rebuilding what is already live. We are past <strong style="color:#fff;">80%+</strong> of core development; capital wins the market and hardens the payment path shoppers and merchants rely on.
                        </p>

                        <div class="ask-grid">
                            <div class="ask-card"><div class="k">Raise</div><div class="v">$750k – $1.5M</div></div>
                            <div class="ask-card"><div class="k">Instrument</div><div class="v">SAFE or priced equity</div></div>
                            <div class="ask-card"><div class="k">Today’s ownership</div><div class="v">No prior share sales</div></div>
                            <div class="ask-card"><div class="k">Build status</div><div class="v">80%+ shipped</div></div>
                        </div>

                        <div style="margin-top: 2rem;">
                            <h3 style="font-size:1.05rem;margin-bottom:0.75rem;">What the money does</h3>
                            <div class="ask-grid" style="margin-top:0.5rem;">
                                <div class="ask-card">
                                    <div class="k">01 · Competitive access</div>
                                    <div class="v" style="font-size:0.95rem;font-weight:600;line-height:1.4;">Sales, merchant acquisition, more tills in more cities, and distribution so Checkout can out-execute acquirers and wallets that only own one side of the loop.</div>
                                </div>
                                <div class="ask-card">
                                    <div class="k">02 · Contactless push</div>
                                    <div class="v" style="font-size:0.95rem;font-weight:600;line-height:1.4;">Roll Cheko tills, expand Proximity Pay, and grow the open Checkout Broadcast Protocol so more phones can pay more shops — faster than typing account numbers.</div>
                                </div>
                                <div class="ask-card">
                                    <div class="k">03 · Security, licensing &amp; compliance</div>
                                    <div class="v" style="font-size:0.95rem;font-weight:600;line-height:1.4;">Harden signed sessions, fraud and identity ops, and partner custody; finish Nigeria’s digital lending license (FCCPC) — so shoppers, merchants, and banks can trust Pay-at-Shop at scale.</div>
                                </div>
                                <div class="ask-card">
                                    <div class="k">04 · Controlled credit*</div>
                                    <div class="v" style="font-size:0.95rem;font-weight:600;line-height:1.4;">Master loan float for merchant/wallet credit — only after FCCPC approval — turning volume into sticky lending.</div>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 2rem;">
                            <h3 style="font-size:1.05rem;margin-bottom:0.75rem;">Use of funds</h3>
                            @foreach ($funds as $i => $f)
                                <div class="fund-row">
                                    <div class="fund-label">{{ $f['label'] }}</div>
                                    <div class="fund-track">
                                        <div class="fund-fill" style="width: {{ $f['pct'] }}%; background: {{ $f['tone'] }}; animation-delay: {{ $i * 0.1 }}s;"></div>
                                    </div>
                                    <div class="fund-pct">{{ $f['pct'] }}%</div>
                                </div>
                            @endforeach
                            <p style="margin-top:0.85rem;font-size:0.8rem;opacity:0.75;">* Credit liquidity after FCCPC registration.</p>
                        </div>

                        @if ($photos['ask_bg']['url'])
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="pitch-foot">
        <div class="wrap">
            <p><strong>Checkout Now LTD</strong> · CheckoutPay · CheckoutNow · Cheko</p>
            <p style="margin-top:0.35rem;">Licensed payments partner: <strong>METRAVON INNOVATION LTD</strong></p>
            <p style="margin-top:0.35rem;"><a href="https://check-outpay.com">check-outpay.com</a></p>
            <p style="margin-top:0.75rem;font-size:0.78rem;">Confidential. Protected under NDA. Do not circulate.</p>
        </div>
    </footer>
    @include('partials.session-keepalive')
</body>
</html>

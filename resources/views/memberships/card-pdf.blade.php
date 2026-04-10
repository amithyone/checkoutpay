<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Card - {{ $subscription->subscription_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            background: #f5f5f5;
        }
        .card {
            width: 204pt; /* 53.98mm - landscape width */
            height: 324pt; /* 85.6mm - landscape height */
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12pt;
            padding: 20pt;
            position: relative;
            overflow: hidden;
            color: #ffffff;
            box-shadow: 0 4pt 12pt rgba(0,0,0,0.15);
        }
        /* Custom background image: show full art, put text on a light panel (dark type) for contrast */
        .card--with-graphics {
            background: #1e293b;
            color: #0f172a;
        }
        .card--with-graphics .card-background {
            opacity: 1;
        }
        .card-background {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-size: cover;
            background-position: center;
        }
        .card-content {
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: stretch;
        }
        .card-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding-right: 15pt;
        }
        .card-left-inner {
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .card--with-graphics .card-left-inner {
            background: rgba(255, 255, 255, 0.96);
            color: #0f172a;
            padding: 10pt 12pt;
            border-radius: 10pt;
            box-shadow: 0 2pt 10pt rgba(0, 0, 0, 0.4);
            border: 0.5pt solid rgba(15, 23, 42, 0.12);
        }
        .card-right {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .card--with-graphics .qr-code {
            box-shadow: 0 2pt 10pt rgba(0, 0, 0, 0.4);
            border: 0.5pt solid rgba(15, 23, 42, 0.12);
        }
        .card-header {
            margin-bottom: 15pt;
        }
        .card-logo {
            max-width: 60pt;
            max-height: 40pt;
        }
        .card-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .card-body {
            flex: 1;
        }
        .member-name {
            font-size: 20pt;
            font-weight: bold;
            margin-bottom: 10pt;
            text-transform: uppercase;
            line-height: 1.2;
            text-shadow: 0 1pt 2pt rgba(0, 0, 0, 0.35);
        }
        .card--with-graphics .member-name {
            color: #0f172a;
            text-shadow: none;
        }
        .membership-name {
            font-size: 14pt;
            opacity: 0.95;
            margin-bottom: 6pt;
            font-weight: 600;
            text-shadow: 0 1pt 2pt rgba(0, 0, 0, 0.25);
        }
        .card--with-graphics .membership-name {
            color: #1e293b;
            opacity: 1;
            text-shadow: none;
        }
        .membership-category {
            font-size: 11pt;
            opacity: 0.9;
            margin-bottom: 8pt;
            text-transform: uppercase;
            font-weight: 500;
            text-shadow: 0 1pt 1pt rgba(0, 0, 0, 0.2);
        }
        .card--with-graphics .membership-category {
            color: #475569;
            opacity: 1;
            text-shadow: none;
        }
        .subscription-number {
            font-size: 10pt;
            opacity: 0.92;
            font-family: 'Courier New', monospace;
            margin-bottom: 15pt;
            text-shadow: 0 1pt 1pt rgba(0, 0, 0, 0.25);
        }
        .card--with-graphics .subscription-number {
            color: #334155;
            opacity: 1;
            text-shadow: none;
        }
        .card-footer {
            margin-top: auto;
        }
        .expiry-section {
            margin-bottom: 10pt;
        }
        .expiry-label {
            font-size: 8pt;
            opacity: 0.85;
            margin-bottom: 4pt;
            text-transform: uppercase;
        }
        .card--with-graphics .expiry-label {
            color: #64748b;
            opacity: 1;
        }
        .expiry-date {
            font-size: 12pt;
            opacity: 0.95;
            font-weight: 600;
            text-shadow: 0 1pt 1pt rgba(0, 0, 0, 0.2);
        }
        .card--with-graphics .expiry-date {
            color: #0f172a;
            opacity: 1;
            text-shadow: none;
        }
        .business-name {
            font-size: 9pt;
            opacity: 0.9;
            margin-top: 10pt;
            text-shadow: 0 1pt 1pt rgba(0, 0, 0, 0.2);
        }
        .card--with-graphics .business-name {
            color: #475569;
            opacity: 1;
            text-shadow: none;
        }
        .qr-code {
            width: 80pt;
            height: 80pt;
            background: white;
            padding: 6pt;
            border-radius: 6pt;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .qr-code img {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
        }
    </style>
</head>
<body>
    @php $hasCardGraphics = (bool) $subscription->membership->card_graphics; @endphp
    <div class="card{{ $hasCardGraphics ? ' card--with-graphics' : '' }}">
        @if($hasCardGraphics)
            <div class="card-background" style="background-image: url('{{ public_path('storage/' . $subscription->membership->card_graphics) }}');"></div>
        @endif

        <div class="card-content">
            <!-- Left Side: Member Info -->
            <div class="card-left">
                <div class="card-left-inner">
                    <div>
                        <div class="card-header">
                        @if($subscription->membership->card_logo)
                            <div class="card-logo">
                                <img src="{{ public_path('storage/' . $subscription->membership->card_logo) }}" alt="Logo">
                            </div>
                        @elseif($subscription->membership->business->logo)
                            <div class="card-logo">
                                <img src="{{ public_path('storage/' . $subscription->membership->business->logo) }}" alt="Logo">
                            </div>
                        @endif
                        </div>

                        <div class="card-body">
                            <div class="member-name">{{ $subscription->member_name }}</div>
                            <div class="membership-name">{{ $subscription->membership->name }}</div>
                            @if($subscription->membership->category)
                            <div class="membership-category">{{ $subscription->membership->category->name }}</div>
                            @endif
                            <div class="subscription-number">{{ $subscription->subscription_number }}</div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <div class="expiry-section">
                            <div class="expiry-label">EXPIRES</div>
                            <div class="expiry-date">{{ $subscription->expires_at->format('M d, Y') }}</div>
                        </div>
                        <div class="business-name">{{ $subscription->membership->business->name }}</div>
                    </div>
                </div>
            </div>

            <!-- Right Side: QR Code -->
            <div class="card-right">
                <div class="qr-code">
                    <img src="data:image/svg+xml;base64,{{ $qrCodeBase64 }}" alt="QR Code">
                </div>
            </div>
        </div>
    </div>
</body>
</html>

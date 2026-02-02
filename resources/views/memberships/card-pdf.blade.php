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
            width: 324pt; /* 85.6mm */
            height: 204pt; /* 53.98mm */
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12pt;
            padding: 20pt;
            position: relative;
            overflow: hidden;
            color: white;
            box-shadow: 0 4pt 12pt rgba(0,0,0,0.15);
        }
        .card-background {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.1;
            background-size: cover;
            background-position: center;
        }
        .card-content {
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
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
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 8pt;
            text-transform: uppercase;
        }
        .membership-name {
            font-size: 12pt;
            opacity: 0.9;
            margin-bottom: 4pt;
        }
        .subscription-number {
            font-size: 10pt;
            opacity: 0.8;
            font-family: 'Courier New', monospace;
        }
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 15pt;
        }
        .expiry-date {
            font-size: 10pt;
            opacity: 0.9;
        }
        .expiry-label {
            font-size: 8pt;
            opacity: 0.7;
            margin-bottom: 2pt;
        }
        .qr-code {
            width: 60pt;
            height: 60pt;
            background: white;
            padding: 4pt;
            border-radius: 4pt;
        }
        .qr-code img {
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body>
    <div class="card">
        @if($subscription->membership->card_graphics)
            <div class="card-background" style="background-image: url('{{ public_path('storage/' . $subscription->membership->card_graphics) }}');"></div>
        @endif
        
        <div class="card-content">
            <div class="card-header">
                <div>
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
                <div class="qr-code">
                    <img src="data:image/png;base64,{{ $qrCodeBase64 }}" alt="QR Code">
                </div>
            </div>

            <div class="card-body">
                <div class="member-name">{{ $subscription->member_name }}</div>
                <div class="membership-name">{{ $subscription->membership->name }}</div>
                <div class="subscription-number">{{ $subscription->subscription_number }}</div>
            </div>

            <div class="card-footer">
                <div>
                    <div class="expiry-label">EXPIRES</div>
                    <div class="expiry-date">{{ $subscription->expires_at->format('M d, Y') }}</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 8pt; opacity: 0.7;">{{ $subscription->membership->business->name }}</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

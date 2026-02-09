<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.6;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
            background: #fff;
        }
        .header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #3C50E0;
        }
        .header-logo-section {
            text-align: center;
            margin-bottom: 20px;
        }
        .header .logo-container {
            width: 100px;
            height: 100px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .header .logo-container img {
            max-width: 100px;
            max-height: 100px;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        .header-top-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .header-left {
            flex: 1;
            text-align: left;
        }
        .header-right {
            flex: 1;
            text-align: right;
        }
        .header .business-name {
            font-size: 18px;
            font-weight: bold;
            color: #1a202c;
            margin-bottom: 5px;
        }
        .header .business-details {
            font-size: 11px;
            color: #666;
            line-height: 1.5;
            word-wrap: break-word;
            word-break: break-word;
            max-width: 350px;
        }
        .header h1 {
            font-size: 36px;
            color: #3C50E0;
            margin: 0 0 5px 0;
            font-weight: bold;
        }
        .header .invoice-label {
            font-size: 14px;
            color: #666;
            margin: 0;
        }
        .invoice-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .info-section {
            flex: 1;
        }
        .info-section h3 {
            font-size: 14px;
            color: #3C50E0;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .info-section p {
            font-size: 11px;
            color: #666;
            margin-bottom: 5px;
        }
        .info-section strong {
            color: #1a202c;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table thead {
            background-color: #3C50E0;
            color: #fff;
        }
        .items-table th {
            padding: 12px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
        }
        .items-table tbody tr:hover {
            background-color: #f9fafb;
        }
        .items-table .text-right {
            text-align: right;
        }
        .items-table .text-center {
            text-align: center;
        }
        .totals-section {
            margin-left: auto;
            width: 300px;
            margin-top: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 11px;
        }
        .total-row.label {
            color: #666;
        }
        .total-row.value {
            font-weight: bold;
            color: #1a202c;
        }
        .total-row.grand-total {
            border-top: 2px solid #3C50E0;
            padding-top: 12px;
            margin-top: 8px;
            font-size: 14px;
            font-weight: bold;
            color: #3C50E0;
        }
        .notes-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        .notes-section h3 {
            font-size: 12px;
            color: #3C50E0;
            margin-bottom: 10px;
        }
        .notes-section p {
            font-size: 11px;
            color: #666;
            line-height: 1.6;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #999;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-paid {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-sent {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-draft {
            background-color: #f3f4f6;
            color: #374151;
        }
        .status-overdue {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .qr-code-section {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background-color: #f9fafb;
            border-radius: 8px;
        }
        .qr-code-section img {
            max-width: 150px;
            height: auto;
            border: 2px solid #e5e7eb;
            border-radius: 4px;
            padding: 10px;
            background: #fff;
        }
        .paid-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 72px;
            font-weight: bold;
            color: rgba(16, 185, 129, 0.15);
            z-index: 1000;
            pointer-events: none;
        }
        .checkoutnow-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            font-weight: bold;
            color: rgba(60, 80, 224, 0.2);
            z-index: 999;
            pointer-events: none;
        }
        .paid-badge-large {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #10b981;
            color: #ffffff;
            margin-bottom: 10px;
        }
        .status-ribbon {
            position: absolute;
            top: 20px;
            right: -40px;
            width: 200px;
            padding: 15px 0;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #ffffff;
            text-transform: uppercase;
            transform: rotate(45deg);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 1001;
        }
        .status-ribbon.paid {
            background-color: #10b981;
        }
        .status-ribbon.unpaid {
            background-color: #ef4444;
        }
        .status-ribbon-container {
            position: relative;
            overflow: hidden;
        }
        .payment-button {
            display: inline-block;
            background-color: #3C50E0;
            color: #ffffff !important;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
            margin: 15px 0;
            text-align: center;
        }
        .payment-link-text {
            font-weight: bold;
            font-size: 12px;
            color: #3C50E0;
            word-break: break-all;
            margin-top: 10px;
        }
        .powered-by {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        .powered-by-text {
            font-size: 10px;
            color: #999;
        }
        .powered-by-logo {
            max-height: 20px;
            max-width: 120px;
        }
    </style>
</head>
<body>
    <div class="invoice-container status-ribbon-container" style="position: relative;">
        <!-- Status Ribbon Badge -->
        @if(isset($isPaid) && $isPaid)
        <div class="status-ribbon paid">PAID</div>
        @else
        <div class="status-ribbon unpaid">UNPAID</div>
        @endif
        
        <!-- CheckOutNow Watermark (always shown) -->
        <div class="checkoutnow-watermark">CheckOutNow</div>
        @if(isset($isPaid) && $isPaid)
        <div class="paid-watermark">PAID</div>
        @endif
        <!-- Header -->
        <div class="header">
            <!-- Logo Section (First, Centered at Top) -->
            <div class="header-logo-section">
                @if($invoice->logo && Storage::disk('public')->exists($invoice->logo))
                <div class="logo-container">
                    <img src="{{ storage_path('app/public/' . $invoice->logo) }}" alt="Logo">
                </div>
                @endif
            </div>
            
            <!-- Top Row: Business Name (Left) and Invoice Title (Right) - Aligned at Top -->
            <div class="header-top-row">
                <!-- Business Name Section (Left) -->
                <div class="header-left">
                    <div class="business-name">{{ $invoice->business->name }}</div>
                </div>
                
                <!-- Invoice Heading Section (Right) -->
                <div class="header-right">
                    <h1>INVOICE</h1>
                </div>
            </div>
            
            <!-- Second Row: Business Details (Left) and Invoice Number (Right) -->
            <div class="header-top-row">
                <!-- Business Details Section (Left, Below Business Name) -->
                <div class="header-left">
                    <div class="business-details">
                        @if($invoice->business->email)
                            {{ $invoice->business->email }}@if($invoice->business->phone || $invoice->business->address)<br>@endif
                        @endif
                        @if($invoice->business->phone)
                            {{ $invoice->business->phone }}@if($invoice->business->address)<br>@endif
                        @endif
                        @if($invoice->business->address)
                            {{ $invoice->business->address }}
                        @endif
                    </div>
                </div>
                
                <!-- Invoice Number Section (Right, Below Invoice Title) -->
                <div class="header-right">
                    <div class="invoice-label">Invoice #{{ $invoice->invoice_number }}</div>
                </div>
            </div>
        </div>

        <!-- Invoice Info -->
        <div class="invoice-info">
            <div class="info-section">
                <h3>Bill To</h3>
                <p><strong>{{ $invoice->client_name }}</strong></p>
                @if($invoice->client_company)
                    <p>{{ $invoice->client_company }}</p>
                @endif
                <p>{{ $invoice->client_email }}</p>
                @if($invoice->client_phone)
                    <p>{{ $invoice->client_phone }}</p>
                @endif
                @if($invoice->client_address)
                    <p>{{ $invoice->client_address }}</p>
                @endif
                @if($invoice->client_tax_id)
                    <p>Tax ID: {{ $invoice->client_tax_id }}</p>
                @endif
            </div>
            <div class="info-section" style="text-align: right;">
                <h3>Invoice Details</h3>
                <p><strong>Invoice Date:</strong> {{ $invoice->invoice_date->format('F d, Y') }}</p>
                @if($invoice->due_date)
                    <p><strong>Due Date:</strong> {{ $invoice->due_date->format('F d, Y') }}</p>
                @endif
                @if($invoice->reference_number)
                    <p><strong>Reference:</strong> {{ $invoice->reference_number }}</p>
                @endif
                <p>
                    @if(isset($isPaid) && $isPaid)
                        <span class="paid-badge-large">PAID</span>
                    @else
                        <span class="status-badge status-{{ $invoice->status }}">
                            {{ ucfirst($invoice->status) }}
                        </span>
                    @endif
                </p>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 45%;">Description</th>
                    <th class="text-center" style="width: 10%;">Qty</th>
                    <th class="text-right" style="width: 15%;">Unit Price</th>
                    <th class="text-right" style="width: 15%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $item->description }}
                        @if($item->notes)
                            <br><small style="color: #999;">{{ $item->notes }}</small>
                        @endif
                    </td>
                    <td class="text-center">{{ number_format($item->quantity, 2) }} {{ $item->unit }}</td>
                    <td class="text-right">{{ $invoice->currency }} {{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">{{ $invoice->currency }} {{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-section">
            <div class="total-row">
                <span class="label">Subtotal:</span>
                <span class="value">{{ $invoice->currency }} {{ number_format($invoice->subtotal, 2) }}</span>
            </div>
            @if($invoice->tax_rate > 0)
            <div class="total-row">
                <span class="label">Tax ({{ number_format($invoice->tax_rate, 2) }}%):</span>
                <span class="value">{{ $invoice->currency }} {{ number_format($invoice->tax_amount, 2) }}</span>
            </div>
            @endif
            @if($invoice->discount_amount > 0)
            <div class="total-row">
                <span class="label">
                    Discount
                    @if($invoice->discount_type === 'percentage')
                        ({{ number_format($invoice->discount_amount, 2) }}%)
                    @endif
                    :
                </span>
                <span class="value">- {{ $invoice->currency }} {{ number_format($invoice->discount_amount, 2) }}</span>
            </div>
            @endif
            <div class="total-row grand-total">
                <span>Total Amount:</span>
                <span>{{ $invoice->currency }} {{ number_format($invoice->total_amount, 2) }}</span>
            </div>
            @if($invoice->isPaid())
            <div class="total-row" style="margin-top: 10px;">
                <span class="label" style="color: #10b981;">Paid Amount:</span>
                <span class="value" style="color: #10b981;">{{ $invoice->currency }} {{ number_format($invoice->paid_amount ?? $invoice->total_amount, 2) }}</span>
            </div>
            @endif
        </div>

        <!-- QR Code Section (only show if not paid) -->
        @if(!isset($isPaid) || !$isPaid)
            @if(isset($qrCodeBase64) && $qrCodeBase64)
            <div class="qr-code-section">
                <h3 style="font-size: 14px; color: #3C50E0; margin-bottom: 15px; text-transform: uppercase;">Scan QR Code to Pay</h3>
                <img src="{{ $qrCodeBase64 }}" alt="Payment QR Code">
                <div style="margin-top: 20px;">
                    <a href="{{ $invoice->payment_link_url }}" class="payment-button">Click Here to Pay</a>
                </div>
                <div class="payment-link-text">
                    {{ $invoice->payment_link_url }}
                </div>
            </div>
            @else
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $invoice->payment_link_url }}" class="payment-button">Click Here to Pay</a>
                <div class="payment-link-text">
                    {{ $invoice->payment_link_url }}
                </div>
            </div>
            @endif
        @endif

        <!-- Notes and Terms -->
        @if($invoice->notes || $invoice->terms_and_conditions)
        <div class="notes-section">
            @if($invoice->notes)
            <div style="margin-bottom: 20px;">
                <h3>Notes</h3>
                <p>{{ $invoice->notes }}</p>
            </div>
            @endif
            @if($invoice->terms_and_conditions)
            <div>
                <h3>Terms & Conditions</h3>
                <p>{{ $invoice->terms_and_conditions }}</p>
            </div>
            @endif
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p>This is a computer-generated invoice. No signature required.</p>
            <p>Generated on {{ now()->format('F d, Y \a\t g:i A') }}</p>
            <div class="powered-by">
                <span class="powered-by-text">Powered by</span>
                @if(file_exists(public_path('logo.png')))
                <img src="{{ public_path('logo.png') }}" alt="CheckOutNow" class="powered-by-logo">
                @else
                <span class="powered-by-text" style="font-weight: bold; color: #3C50E0;">CheckOutNow</span>
                @endif
            </div>
        </div>
    </div>
</body>
</html>

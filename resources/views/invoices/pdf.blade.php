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
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #3C50E0;
        }
        .header-left {
            display: flex;
            align-items: flex-start;
            gap: 20px;
        }
        .header-left .logo-container {
            max-width: 150px;
            max-height: 80px;
        }
        .header-left .logo-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .header-left .invoice-info {
            flex: 1;
        }
        .header-left h1 {
            font-size: 32px;
            color: #3C50E0;
            margin-bottom: 5px;
        }
        .header-left .invoice-label {
            font-size: 14px;
            color: #666;
        }
        .header-right {
            text-align: right;
        }
        .header-right .business-name {
            font-size: 18px;
            font-weight: bold;
            color: #1a202c;
            margin-bottom: 5px;
        }
        .header-right .business-details {
            font-size: 11px;
            color: #666;
            line-height: 1.5;
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
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                @if($invoice->logo && Storage::disk('public')->exists($invoice->logo))
                <div class="logo-container">
                    <img src="{{ storage_path('app/public/' . $invoice->logo) }}" alt="Logo">
                </div>
                @endif
                <div class="invoice-info">
                    <h1>INVOICE</h1>
                    <div class="invoice-label">Invoice #{{ $invoice->invoice_number }}</div>
                </div>
            </div>
            <div class="header-right">
                <div class="business-name">{{ $invoice->business->name }}</div>
                <div class="business-details">
                    @if($invoice->business->email)
                        {{ $invoice->business->email }}<br>
                    @endif
                    @if($invoice->business->phone)
                        {{ $invoice->business->phone }}<br>
                    @endif
                    @if($invoice->business->address)
                        {{ $invoice->business->address }}
                    @endif
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
                    <span class="status-badge status-{{ $invoice->status }}">
                        {{ ucfirst($invoice->status) }}
                    </span>
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
        </div>
    </div>
</body>
</html>

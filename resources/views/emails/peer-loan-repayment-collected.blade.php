<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $role === 'lender' ? 'Loan repayment received' : 'Loan repayment collected' }} - {{ $appName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f5f7fa; line-height: 1.6; color: #333; }
        .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.08); }
        .email-header { background: linear-gradient(135deg, #3C50E0 0%, #2E40C7 100%); padding: 32px 24px; text-align: center; color: #fff; }
        .email-header h1 { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .email-header .subtitle { color: rgba(255,255,255,0.9); font-size: 14px; }
        .email-body { padding: 32px 24px; }
        .greeting { font-size: 17px; font-weight: 600; color: #1a202c; margin-bottom: 16px; }
        .content-text { font-size: 14px; color: #4a5568; margin-bottom: 18px; line-height: 1.7; }
        .amount-card { background: linear-gradient(135deg, #e8edff 0%, #d6deff 100%); border: 2px solid #3C50E0; border-radius: 12px; padding: 24px; margin: 22px 0; text-align: center; }
        .amount-card .amount { font-size: 30px; font-weight: 700; color: #1e293b; }
        .amount-card .caption { font-size: 13px; color: #475569; margin-top: 6px; }
        .info-grid { display: grid; gap: 12px; margin: 18px 0; }
        .info-item { background-color: #f7fafc; padding: 12px 14px; border-radius: 8px; border-left: 4px solid #3C50E0; }
        .info-item .label { font-size: 11px; color: #718096; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.4px; }
        .info-item .value { font-size: 15px; font-weight: 600; color: #1a202c; }
        .balance-box { background-color: #f0f4ff; border: 2px solid #3C50E0; border-radius: 8px; padding: 18px; margin: 22px 0; text-align: center; }
        .balance-box .label { font-size: 13px; color: #475569; }
        .balance-box .balance { font-size: 22px; font-weight: 700; color: #3C50E0; margin-top: 4px; }
        .email-footer { background-color: #1a202c; padding: 22px; text-align: center; color: #a0aec0; font-size: 12px; }
        @media only screen and (max-width: 600px) {
            .email-body { padding: 26px 18px; }
            .email-header { padding: 26px 18px; }
        }
    </style>
</head>
<body>
<div style="padding: 20px;">
    <div class="email-container">
        <div class="email-header">
            <h1>{{ $appName }}</h1>
            <div class="subtitle">{{ $role === 'lender' ? 'Loan repayment received' : 'Loan repayment collected' }}</div>
        </div>
        <div class="email-body">
            <div class="greeting">Hello {{ $business->name }},</div>
            <div class="content-text">
                @if($role === 'lender')
                    A scheduled installment from <strong>{{ $loan->borrower->name }}</strong> has been collected and credited to your balance.
                @else
                    A scheduled installment on your business loan from <strong>{{ $loan->offer->lender->name }}</strong> has been collected from your balance.
                @endif
            </div>

            <div class="amount-card">
                <div style="font-size: 12px; color: #475569; letter-spacing: 0.4px; text-transform: uppercase; margin-bottom: 6px;">Amount {{ $role === 'lender' ? 'received' : 'collected' }}</div>
                <div class="amount">₦{{ number_format($amountCollected, 2) }}</div>
                <div class="caption">Installment #{{ $schedule->sequence }} · due {{ $schedule->due_at->format('M d, Y') }}</div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Loan</div>
                    <div class="value">#{{ $loan->id }} · principal ₦{{ number_format($loan->principal, 2) }}</div>
                </div>
                <div class="info-item">
                    <div class="label">{{ $role === 'lender' ? 'Borrower' : 'Lender' }}</div>
                    <div class="value">{{ $role === 'lender' ? $loan->borrower->name : $loan->offer->lender->name }}</div>
                </div>
                <div class="info-item">
                    <div class="label">Installment status</div>
                    <div class="value">{{ ucfirst($schedule->status) }} · paid ₦{{ number_format($schedule->amount_paid, 2) }} of ₦{{ number_format($schedule->amount_due, 2) }}</div>
                </div>
                <div class="info-item">
                    <div class="label">Installment remaining</div>
                    <div class="value">₦{{ number_format($remainingOnSchedule, 2) }}</div>
                </div>
                <div class="info-item">
                    <div class="label">Loan remaining (all installments)</div>
                    <div class="value">₦{{ number_format($remainingOnLoan, 2) }}</div>
                </div>
            </div>

            <div class="balance-box">
                <div class="label">Your account balance</div>
                <div class="balance">₦{{ number_format($business->balance, 2) }}</div>
            </div>

            <div class="content-text">
                @if($remainingOnLoan <= 0.01)
                    @if($role === 'lender')
                        This loan is fully repaid. No further collections will run for it.
                    @else
                        Your loan is fully repaid. Thank you.
                    @endif
                @else
                    @if($role === 'lender')
                        Subsequent installments will be collected on their due dates as the borrower's balance allows.
                    @else
                        The next installment will be collected automatically on its due date if your balance permits. You can pre-fund your account to avoid overdue status.
                    @endif
                @endif
            </div>
        </div>
        <div class="email-footer">© {{ date('Y') }} {{ $appName }}. All rights reserved.</div>
    </div>
</div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transfer confirmed</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 28rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: 0.95rem; }
        th, td { text-align: left; padding: 0.35rem 0; vertical-align: top; }
        th { color: #555; font-weight: 600; width: 38%; }
        td { word-break: break-word; }
        .muted { color: #555; font-size: 0.9rem; }
    </style>
</head>
<body>
    <h1>Done</h1>
    @if(!empty($message))
        <p>{{ $message }}</p>
    @else
        <p>Your transfer was confirmed.</p>
    @endif

    @if(is_array($receipt) && $receipt !== [])
        <h2 style="font-size:1.1rem;margin-top:1.25rem;">Receipt</h2>
        <table>
            @foreach(\App\Services\Whatsapp\WhatsappBankTransferReceiptDetails::webReceiptRows($receipt) as $label => $value)
                <tr>
                    <th>{{ $label }}</th>
                    <td>{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if(isset($balance_after) && $balance_after !== null)
        <p class="muted">Wallet balance: ₦{{ number_format((float) $balance_after, 2) }}</p>
    @endif

    <p class="muted">Check WhatsApp for your updated balance. You can close this page.</p>
</body>
</html>

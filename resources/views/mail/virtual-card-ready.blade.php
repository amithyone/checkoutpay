<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5;">
    <p>Your <strong>{{ $brandName }}</strong> Dollar Virtual Card is ready to use.</p>
    <p><strong>Card name:</strong> {{ $cardName }}</p>
    @if ($balanceUsd !== null)
        <p><strong>Starting balance:</strong> ${{ number_format($balanceUsd, 2) }} USD</p>
    @endif
    <p>Open the app to fund, view details, or freeze your card when you are not using it.</p>
    <p>— {{ $brandName }}</p>
</body>
</html>

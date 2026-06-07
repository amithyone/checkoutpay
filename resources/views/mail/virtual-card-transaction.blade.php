<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5;">
    <p><strong>{{ $headline }}</strong></p>
    <p>{{ $summaryLine }}</p>
    <p><strong>Time:</strong> {{ $whenLine }}</p>
    @if ($statusLine)
        <p><strong>Status:</strong> {{ $statusLine }}</p>
    @endif
    @if ($referenceLine)
        <p><strong>Reference:</strong> {{ $referenceLine }}</p>
    @endif
    <p>Open the app to view your full card history or freeze your card.</p>
    <p>— {{ $brandName }}</p>
</body>
</html>

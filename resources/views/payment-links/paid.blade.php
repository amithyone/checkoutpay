@php
    $siteName = \App\Models\Setting::get('site_name', 'CheckoutPay');
    $initials = collect(preg_split('/\s+/', trim((string) $link->business->name)))
        ->filter()
        ->take(2)
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Paid — {{ $link->title }}</title>
    @include('partials.tailwind-assets')
    @include('payment-links.partials.payer-styles')
</head>
<body class="pl-app">
    <div class="pl-atmosphere" aria-hidden="true">
        <div class="pl-blob pl-blob-a"></div>
        <div class="pl-blob pl-blob-b"></div>
        <div class="pl-blob pl-blob-c"></div>
    </div>
    <div class="pl-shell">
        <div class="pl-brand">{{ $siteName }}</div>
        <div class="pl-hero">
            <div class="pl-avatar">{{ $initials !== '' ? $initials : 'P' }}</div>
            <div>
                <h1>{{ $link->business->name }}</h1>
                <p>{{ $link->title }}</p>
            </div>
        </div>
        <div class="pl-card pl-center">
            <i class="fas fa-check-circle" style="color:#22c55e"></i>
            <h2>This link has been paid</h2>
            <p class="pl-hint">{{ $link->title }} for {{ $link->business->name }} is closed after a successful payment.</p>
        </div>
    </div>
    @include('payment-links.partials.create-own')
</body>
</html>

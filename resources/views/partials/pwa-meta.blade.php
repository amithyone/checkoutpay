@php
    $pwaName = config('app.name', 'CheckoutPay');
    $pwaIcon = null; // Landing page logo first, then favicon (PNG/JPG/SVG all work)
    try {
        if (class_exists(\App\Models\Setting::class)) {
            $pwaName = \App\Models\Setting::get('site_name', $pwaName);
            $pwaIcon = \App\Models\Setting::get('site_logo') ?: \App\Models\Setting::get('site_favicon');
        }
    } catch (\Throwable $e) {
        // Settings table may not exist during setup
    }
@endphp
{{-- PWA: Web App Manifest (Android & desktop) --}}
<link rel="manifest" href="{{ url('/manifest.json') }}">
<meta name="theme-color" content="#3C50E0">
<meta name="mobile-web-app-capable" content="yes">

{{-- PWA: iOS-specific meta and icons --}}
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $pwaName }}">
@if($pwaIcon)
    <link rel="apple-touch-icon" href="{{ asset('storage/' . $pwaIcon) }}">
@else
    <link rel="apple-touch-icon" href="{{ asset('images/pwa/icon-192.png') }}">
@endif

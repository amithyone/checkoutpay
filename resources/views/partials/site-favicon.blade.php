@php
    $favicon = $favicon ?? \App\Models\Setting::get('site_favicon');
    $faviconPath = $favicon ? storage_path('app/public/' . $favicon) : null;
    $faviconExists = $favicon && $faviconPath && file_exists($faviconPath);
    $faviconExt = $faviconExists ? strtolower(pathinfo($favicon, PATHINFO_EXTENSION)) : null;
    $faviconMime = match ($faviconExt) {
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'jpg', 'jpeg' => 'image/jpeg',
        default => 'image/png',
    };
    $logo = \App\Models\Setting::get('site_logo');
    $logoPath = $logo ? storage_path('app/public/' . $logo) : null;
    $logoExists = $logo && $logoPath && file_exists($logoPath);
@endphp
@if($faviconExists)
    <link rel="icon" type="{{ $faviconMime }}" href="{{ asset('storage/' . $favicon) }}">
    <link rel="shortcut icon" type="{{ $faviconMime }}" href="{{ asset('storage/' . $favicon) }}">
@else
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('favicon.png') }}">
@endif

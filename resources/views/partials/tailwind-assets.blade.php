{{-- Production Tailwind: run npm run build:css after changing Blade classes. --}}
@php
    $tailwindCss = public_path('css/app.css');
    $tailwindVer = is_file($tailwindCss) ? (string) filemtime($tailwindCss) : '1';
@endphp
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ $tailwindVer }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @yield('title')
    @hasSection('favicon')
        @yield('favicon')
    @else
        @include('partials.site-favicon')
    @endif
    @include('partials.pwa-meta')
    @yield('seo')
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    @include('partials.tailwind-assets')
    @stack('head')
</head>
<body class="bg-surface text-midnight-deep antialiased font-sans">
    <div class="relative min-h-screen">
        <div class="absolute inset-0 marketing-grid-bg pointer-events-none opacity-60" aria-hidden="true"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-white/90 via-surface/50 to-surface pointer-events-none" aria-hidden="true"></div>
        <div class="relative z-10">
            @include('partials.nav')
            <main>
                @yield('content')
            </main>
            @include('partials.footer')
        </div>
    </div>
    @stack('scripts')
</body>
</html>

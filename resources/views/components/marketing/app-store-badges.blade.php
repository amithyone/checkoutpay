@props([
    'compact' => false,
    'heading' => null,
    'subheading' => null,
    'showWebFallback' => true,
])

@php
    use App\Support\CheckoutNowApp;

    $brand = CheckoutNowApp::brandName();
    $playUrl = CheckoutNowApp::playStoreUrl();
    $appStoreUrl = CheckoutNowApp::appStoreUrl();
    $webUrl = CheckoutNowApp::webUrl();
@endphp

<div {{ $attributes->merge(['class' => 'space-y-4']) }}>
    @if($heading || $subheading)
        <div class="space-y-1">
            @if($heading)
                <p class="text-sm font-bold text-midnight-deep">{{ $heading }}</p>
            @endif
            @if($subheading)
                <p class="text-xs text-slate-500 font-medium">{{ $subheading }}</p>
            @endif
        </div>
    @else
        <div class="space-y-1">
            <span class="inline-flex items-center gap-1.5 bg-brand-primary/10 text-brand-primary text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-md tracking-wide">
                <i class="fas fa-mobile-alt"></i> {{ $brand }} app
            </span>
            <p class="text-xs text-slate-500 font-semibold pt-1">
                Available on Google Play and the App Store — wallet, bills, transfers, and your dollar virtual card in one app.
            </p>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row gap-3 {{ $compact ? 'max-w-md' : '' }}">
        <a
            href="{{ $appStoreUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="flex-1 flex items-center gap-3 bg-midnight-deep hover:bg-slate-800 text-white px-4 py-3 rounded-xl transition-all shadow-premium border border-slate-800 group min-w-[160px]"
            aria-label="Download {{ $brand }} on the App Store"
        >
            <svg class="w-7 h-7 shrink-0 group-hover:scale-105 transition-transform" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M18.71,19.5C17.88,20.74 17,21.95 15.66,21.97C14.32,22 13.89,21.18 12.37,21.18C10.84,21.18 10.37,21.95 9.1,22C7.79,22.05 6.8,20.68 5.96,19.47C4.25,17 2.94,12.45 4.7,9.39C5.57,7.87 7.13,6.91 8.82,6.88C10.1,6.86 11.32,7.75 12.11,7.75C12.89,7.75 14.37,6.68 15.92,6.84C16.57,6.87 18.39,7.1 19.56,8.82C19.47,8.88 17.39,10.1 17.41,12.63C17.44,15.65 20.06,16.66 20.1,16.67C20.08,16.74 19.67,18.11 18.71,19.5M15.97,4.17C16.63,3.37 17.07,2.28 16.95,1C16,1.04 14.9,1.6 14.24,2.38C13.68,3.04 13.19,4.14 13.34,5.39C14.39,5.47 15.4,4.88 15.97,4.17Z"/>
            </svg>
            <div class="text-left">
                <p class="text-[9px] text-slate-400 uppercase font-bold leading-none">Download on the</p>
                <p class="text-sm font-bold text-white leading-tight">App Store</p>
            </div>
        </a>

        <a
            href="{{ $playUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="flex-1 flex items-center gap-3 bg-midnight-deep hover:bg-slate-800 text-white px-4 py-3 rounded-xl transition-all shadow-premium border border-slate-800 group min-w-[160px]"
            aria-label="Get {{ $brand }} on Google Play"
        >
            <svg class="w-7 h-7 shrink-0 group-hover:scale-105 transition-transform" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M3,5.27V18.73L16.55,12L3,5.27M17.87,11.33L19.85,12.33C20.25,12.53 20.25,13.1 19.85,13.3L17.87,14.3L15,12.87L17.87,11.33M3,3C3.4,3 3.8,3.13 4.13,3.4L18.8,10.73L14.4,12.93L3,3M3,21L14.4,11.07L18.8,13.27L4.13,20.6C3.8,20.87 3.4,21 3,21Z"/>
            </svg>
            <div class="text-left">
                <p class="text-[9px] text-slate-400 uppercase font-bold leading-none">Get it on</p>
                <p class="text-sm font-bold text-white leading-tight">Google Play</p>
            </div>
        </a>
    </div>

    @if($showWebFallback)
        <p class="text-[11px] text-slate-400 font-medium">
            Prefer a browser?
            <a href="{{ $webUrl }}" target="_blank" rel="noopener noreferrer" class="text-brand-primary font-semibold hover:underline">Open {{ $brand }} web app</a>
            @unless(CheckoutNowApp::hasConfiguredAppStoreUrl())
                <span class="text-slate-400"> · App Store listing coming soon — use the web app in the meantime.</span>
            @endunless
        </p>
    @endif
</div>

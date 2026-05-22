@php
    $seo = $seo ?? \App\Support\Seo::resolve($seoOverrides ?? []);
@endphp
<meta name="description" content="{{ $seo['description'] }}">
<meta name="keywords" content="{{ $seo['keywords'] }}">
<meta name="robots" content="{{ ($seo['noindex'] ?? false) ? 'noindex, nofollow' : 'index, follow, max-image-preview:large' }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
<meta name="author" content="{{ config('seo.site_name', 'CheckoutPay') }}">
<meta name="geo.region" content="NG">
<meta name="geo.placename" content="Nigeria">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('seo.site_name', 'CheckoutPay') }}">
<meta property="og:locale" content="{{ config('seo.locale', 'en_NG') }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta property="og:image" content="{{ $seo['image'] }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo['title'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
<meta name="twitter:image" content="{{ $seo['image'] }}">
@if(config('seo.twitter_handle'))
<meta name="twitter:site" content="{{ config('seo.twitter_handle') }}">
@endif
<script type="application/ld+json">{!! json_encode(\App\Support\Seo::organizationJsonLd(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/ld+json">{!! json_encode(\App\Support\Seo::websiteJsonLd(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

@php
    $seoPath = $seoPath ?? '/';
    $seo = $seo ?? \App\Support\Seo::forPath($seoPath);
@endphp
<title>{{ $seo['title'] }}</title>
@include('partials.site-favicon')
@include('partials.seo-head', ['seo' => $seo, 'jsonLdExtra' => $jsonLdExtra ?? []])

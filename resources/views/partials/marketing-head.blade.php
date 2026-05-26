@php
    $seoPath = $seoPath ?? '/';
    $seo = $seo ?? \App\Support\Seo::forPath($seoPath);
@endphp
<title>{{ $seo['title'] }}</title>
@if(\App\Models\Setting::get('site_favicon'))
    <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
@endif
@include('partials.seo-head', ['seo' => $seo, 'jsonLdExtra' => $jsonLdExtra ?? []])

@extends('layouts.marketing')

@section('title')
    <title>{{ $page->meta_title ?? config('seo.default_title') }}</title>
@endsection

@section('seo')
    @php
        $jsonLdExtra = [\App\Support\FaqCatalog::faqPageJsonLd(\App\Support\FaqCatalog::forCategory('payment-gateway'))];
    @endphp
    @include('partials.seo-head', ['seoOverrides' => [
        'title' => $page->meta_title ?? config('seo.default_title'),
        'description' => $page->meta_description ?? config('seo.default_description'),
        'path' => '/',
    ], 'jsonLdExtra' => $jsonLdExtra])
@endsection

@section('content')
    @php
        $hero = $content['hero'] ?? [];
        $pricingSection = $content['pricing_section'] ?? [];
        $howItWorks = $content['how_it_works'] ?? [];
    @endphp

    <x-marketing.hero :hero="$hero" />
    <x-marketing.whatsapp-wallet-section />
    <x-marketing.virtual-card-section :virtual-card="$virtualCard ?? []" />
    <x-marketing.commerce-infrastructure />
    <x-marketing.woocommerce-section />
    <x-marketing.pricing-calculator :pricing-section="$pricingSection" />
    <x-marketing.how-it-works-faq :how-it-works="$howItWorks" />
@endsection

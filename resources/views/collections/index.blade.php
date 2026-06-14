@extends('layouts.marketing')

@section('title')
    @include('partials.marketing-head', [
        'seoPath' => '/collections',
        'jsonLdExtra' => [\App\Support\FaqCatalog::faqPageJsonLd(\App\Support\FaqCatalog::forCategory('payouts-collections'))],
    ])
@endsection

@section('content')
    <x-marketing.product-hero
        badge="Payment collections"
        icon="fa-wallet"
        title="Payment Collections"
        subtitle="Accept payments from customers through multiple channels and track every naira."
    />

    <x-marketing.product-section bg="white">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8 max-w-4xl mx-auto">
            <x-marketing.product-card
                href="{{ route('developers.index') }}"
                icon="fa-code"
                title="API Integration"
                description="Integrate payments directly into your application with webhooks and real-time status."
            />
            <x-marketing.product-card
                href="{{ route('checkout-demo.index') }}"
                icon="fa-globe"
                title="Hosted Checkout"
                description="Redirect customers to our secure, mobile-optimized payment page."
            />
        </div>
    </x-marketing.product-section>

    @include('partials.faq-section', ['category' => 'payouts-collections', 'title' => 'Collections & payouts FAQs'])

    <x-marketing.product-cta
        title="Ready to collect payments?"
        :primary-url="route('business.register')"
        :secondary-url="route('pricing')"
    />
@endsection

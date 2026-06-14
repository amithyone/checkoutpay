@extends('layouts.marketing')

@section('title')
    @include('partials.marketing-head', [
        'seoPath' => '/payout',
        'jsonLdExtra' => [\App\Support\FaqCatalog::faqPageJsonLd(\App\Support\FaqCatalog::forCategory('payouts-collections'))],
    ])
@endsection

@section('content')
    <x-marketing.product-hero
        badge="Business payouts"
        icon="fa-money-bill-wave"
        title="Payout Solutions"
        subtitle="Withdraw your funds quickly and securely to your bank account."
    />

    <x-marketing.product-section bg="white">
        <div class="card-marketing p-6 sm:p-8 max-w-2xl mx-auto">
            <h2 class="text-xl font-bold text-midnight-deep mb-4">Features</h2>
            <ul class="space-y-3 text-slate-600 font-medium">
                <li class="flex items-start gap-3"><i class="fas fa-check-circle text-brand-primary mt-0.5"></i> Fast withdrawals to Nigerian bank accounts</li>
                <li class="flex items-start gap-3"><i class="fas fa-check-circle text-brand-primary mt-0.5"></i> Secure processing with verification</li>
                <li class="flex items-start gap-3"><i class="fas fa-check-circle text-brand-primary mt-0.5"></i> Auto-withdrawal and manual payout options</li>
            </ul>
        </div>
    </x-marketing.product-section>

    @include('partials.faq-section', ['category' => 'payouts-collections', 'title' => 'Payouts & collections FAQs'])

    <x-marketing.product-cta
        title="Start accepting payments"
        subtitle="Create a business account to collect and withdraw funds."
        :primary-url="route('business.register')"
    />
@endsection

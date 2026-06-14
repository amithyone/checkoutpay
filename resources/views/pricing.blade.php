@extends('layouts.marketing')

@section('title')
    <title>{{ $page->meta_title ?? 'Pricing — Affordable Payment Gateway Nigeria | CheckoutPay' }}</title>
@endsection

@section('seo')
    @include('partials.seo-head', ['seoOverrides' => [
        'title' => $page->meta_title ?? 'Pricing — Affordable Payment Gateway Nigeria | CheckoutPay',
        'description' => $page->meta_description ?? 'Transparent low fees for Nigerian merchants: competitive rates, no hidden charges. Compare CheckoutPay — a reliable, cost-effective payment gateway.',
        'path' => '/pricing',
    ], 'jsonLdExtra' => [\App\Support\FaqCatalog::faqPageJsonLd(\App\Support\FaqCatalog::forCategory('payment-gateway'))]])
@endsection

@section('content')
    @php
        use App\Support\MarketingPricing;
        $hero = $content['hero'] ?? [];
        $pricingCard = $content['pricing_card'] ?? [];
        $comparison = $content['comparison'] ?? [];
        $faq = $content['faq'] ?? [];
        $cta = $content['cta'] ?? [];
        $pricingSnapshot = MarketingPricing::snapshot();
    @endphp

    <section class="bg-midnight-deep py-14 sm:py-20">
        <div class="max-w-container mx-auto px-4 sm:px-6 lg:px-8 text-center">
            @if(isset($hero['badge_text']))
            <div class="badge-brand mx-auto mb-6 bg-white/10 border-white/20 text-white">
                <i class="{{ $hero['badge_icon'] ?? 'fas fa-trophy' }}"></i> {{ $hero['badge_text'] }}
            </div>
            @endif
            <h1 class="section-heading text-white mb-4 px-2">
                {{ $hero['title'] ?? 'Simple, Transparent Pricing' }}
            </h1>
            @if(isset($hero['description']))
            <p class="section-subheading mx-auto text-white/70 mb-8 px-2">
                {{ $hero['description'] }}
            </p>
            @endif
            <div class="inline-block card-marketing bg-slate-800/50 border-slate-700 rounded-2xl px-6 py-6 sm:px-10 sm:py-8">
                <div class="flex items-center justify-center space-x-2 sm:space-x-3">
                    <span class="text-4xl sm:text-5xl font-black text-brand-electric">{{ $pricingSnapshot['rate_percentage'] }}</span>
                    <span class="text-2xl text-slate-400 font-bold">+</span>
                    <span class="text-4xl sm:text-5xl font-black text-brand-electric">{{ $pricingSnapshot['rate_fixed'] }}</span>
                </div>
                <p class="text-slate-400 mt-2 text-sm font-semibold">{{ $hero['rate_description'] ?? 'per successful transaction' }}</p>
            </div>
        </div>
    </section>

    @if(isset($pricingCard['plan_name']))
    <section class="py-12 sm:py-16 md:py-20 -mt-6 sm:-mt-8 md:-mt-10">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="card-marketing border-2 border-brand-primary/20 overflow-hidden shadow-brand">
                <div class="bg-gradient-to-r from-brand-primary to-brand-secondary p-6 sm:p-8 md:p-12 text-center text-white">
                    @if(isset($pricingCard['badge_text']))
                    <div class="inline-block bg-white/20 backdrop-blur-sm px-3 py-1.5 sm:px-4 sm:py-2 rounded-full text-xs sm:text-sm font-medium mb-3 sm:mb-4">
                        <i class="fas fa-check-circle mr-1 sm:mr-2"></i> {{ $pricingCard['badge_text'] }}
                    </div>
                    @endif
                    <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-3 sm:mb-4">{{ $pricingCard['plan_name'] }}</h2>
                    @if(isset($pricingCard['description']))
                    <p class="text-base sm:text-lg md:text-xl text-white/80 mb-4 sm:mb-6 px-2 font-medium">{{ $pricingCard['description'] }}</p>
                    @endif
                    <div class="flex items-center justify-center space-x-2 sm:space-x-3 mb-3 sm:mb-4">
                        <span class="text-4xl sm:text-5xl md:text-6xl font-bold">{{ $pricingSnapshot['rate_percentage'] }}</span>
                        <span class="text-2xl sm:text-3xl text-white/80">+</span>
                        <span class="text-4xl sm:text-5xl md:text-6xl font-bold">{{ $pricingSnapshot['rate_fixed'] }}</span>
                    </div>
                    <p class="text-white/70 text-sm sm:text-base font-medium">{{ $pricingCard['rate_description'] ?? 'per transaction' }}</p>
                </div>

                <div class="p-6 sm:p-8 md:p-12">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-10 md:gap-12 mb-8 sm:mb-10 md:mb-12">
                        @if(isset($pricingCard['included']) && is_array($pricingCard['included']))
                        <div>
                            <h3 class="text-xl sm:text-2xl font-bold text-midnight-deep mb-4 sm:mb-6">Everything Included</h3>
                            <div class="space-y-3 sm:space-y-4">
                                @foreach($pricingCard['included'] as $item)
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 w-7 h-7 sm:w-8 sm:h-8 bg-success-green/10 rounded-full flex items-center justify-center mr-3 sm:mr-4">
                                        <i class="fas fa-check text-success-green text-sm"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-sm sm:text-base text-midnight-deep">{{ is_array($item) ? ($item['title'] ?? '') : $item }}</h4>
                                        @if(is_array($item) && isset($item['description']))
                                        <p class="text-xs sm:text-sm text-slate-500 mt-1 font-medium">{{ $item['description'] }}</p>
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @php
                            $pricingExamples = MarketingPricing::pricingPageExamples(
                                $pricingSnapshot['percentage'],
                                $pricingSnapshot['fixed']
                            );
                        @endphp
                        <div>
                            <h3 class="text-xl sm:text-2xl font-bold text-midnight-deep mb-4 sm:mb-6">Pricing Examples</h3>
                            <div class="bg-surface-container-low rounded-xl p-4 sm:p-6 space-y-4 sm:space-y-6 border border-slate-200/80">
                                @foreach($pricingExamples as $example)
                                <div class="flex justify-between items-center pb-3 sm:pb-4 border-b border-slate-200 gap-2">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-sm sm:text-base text-midnight-deep break-words">{{ $example['amount'] ?? '' }}</p>
                                        @if(isset($example['calculation']))
                                        <p class="text-xs sm:text-sm text-slate-500 mt-1 font-medium">{{ $example['calculation'] }}</p>
                                        @endif
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="text-xl sm:text-2xl font-bold text-brand-primary">{{ $example['fee'] ?? '' }}</p>
                                        <p class="text-xs text-slate-400 font-medium">fee</p>
                                    </div>
                                </div>
                                @endforeach
                            </div>

                            <div class="mt-6 sm:mt-8 bg-brand-primary/5 rounded-xl p-4 sm:p-6 border border-brand-primary/20">
                                <h4 class="font-bold text-sm sm:text-base text-midnight-deep mb-3 sm:mb-4">Calculate Your Fees</h4>
                                <div class="space-y-2 sm:space-y-3">
                                    <input type="number" id="calc-amount" placeholder="Enter amount"
                                        class="w-full px-3 sm:px-4 py-2.5 text-sm sm:text-base border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-primary/30 focus:border-brand-primary outline-none bg-white text-midnight-deep">
                                    <div class="flex justify-between items-center pt-2">
                                        <span class="text-sm sm:text-base text-slate-600 font-semibold">Fee:</span>
                                        <span id="calc-fee" class="text-xl sm:text-2xl font-black text-brand-primary">₦0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center pt-6 sm:pt-8 border-t border-slate-200">
                        <a href="{{ route('business.register') }}" class="btn-brand">
                            <i class="fas fa-rocket"></i> {{ $pricingCard['cta_text'] ?? 'Get Started Now' }}
                        </a>
                        @if(isset($pricingCard['cta_note']))
                        <p class="text-xs sm:text-sm text-slate-500 px-2 mt-3 font-medium">{{ $pricingCard['cta_note'] }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
    @endif

    @if(isset($comparison['title']))
    <section class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-10 md:mb-12">
                <h2 class="section-heading mb-3 sm:mb-4">{{ $comparison['title'] }}</h2>
                @if(isset($comparison['subtitle']))
                <p class="section-subheading mx-auto px-2">{{ $comparison['subtitle'] }}</p>
                @endif
            </div>

            @if(isset($comparison['items']) && is_array($comparison['items']))
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8 max-w-5xl mx-auto">
                @foreach($comparison['items'] as $item)
                <div class="card-marketing text-center p-5 sm:p-6">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 bg-brand-primary/10 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                        <i class="{{ $item['icon'] ?? 'fas fa-check' }} text-brand-primary text-xl sm:text-2xl"></i>
                    </div>
                    <h3 class="font-bold text-sm sm:text-base text-midnight-deep mb-2">{{ $item['title'] ?? '' }}</h3>
                    <p class="text-xs sm:text-sm text-slate-500 px-2 font-medium">{{ $item['description'] ?? '' }}</p>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </section>
    @endif

    @if(isset($faq['title']))
    <section class="py-12 sm:py-16 md:py-20 bg-surface-container-low/40">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-10 md:mb-12">
                <h2 class="section-heading mb-3 sm:mb-4">{{ $faq['title'] }}</h2>
            </div>

            @if(isset($faq['items']) && is_array($faq['items']))
            <div class="space-y-4 sm:space-y-6">
                @foreach($faq['items'] as $item)
                <div class="card-marketing p-5 sm:p-6">
                    <h3 class="font-bold text-base sm:text-lg text-midnight-deep mb-2">{{ $item['question'] ?? '' }}</h3>
                    <p class="text-sm sm:text-base text-slate-600 font-medium">{{ $item['answer'] ?? '' }}</p>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </section>
    @endif

    @if(isset($cta['title']))
    <x-marketing.product-cta
        :title="$cta['title']"
        :subtitle="$cta['description'] ?? null"
        :primary-url="route('business.register')"
        :primary-label="$cta['cta_text'] ?? 'Create Your Account'"
    />
    @endif

    @include('partials.faq-section', [
        'category' => 'payment-gateway',
        'title' => 'Payment gateway & pricing FAQs',
    ])
@endsection

@push('scripts')
    <script>
        const calcInput = document.getElementById('calc-amount');
        const calcFee = document.getElementById('calc-fee');
        const pct = {{ json_encode($pricingSnapshot['percentage']) }};
        const fixed = {{ json_encode($pricingSnapshot['fixed']) }};

        if (calcInput && calcFee) {
            calcInput.addEventListener('input', function() {
                const amount = parseFloat(this.value) || 0;
                if (amount > 0) {
                    const fee = Math.round((amount * pct / 100) + fixed);
                    calcFee.textContent = '₦' + fee.toLocaleString('en-NG');
                } else {
                    calcFee.textContent = '₦0';
                }
            });
        }
    </script>
@endpush

@props(['howItWorks' => []])

@php
    use App\Support\MarketingAssets;

    $defaultSteps = [
        ['title' => 'Create Account', 'description' => 'Sign up for free in minutes.'],
        ['title' => 'Integrate', 'description' => 'Connect via API or Plugin.'],
        ['title' => 'Accept Payments', 'description' => 'Start receiving NGN instantly.'],
        ['title' => 'Get Paid', 'description' => 'Automated payouts to your bank.'],
    ];
    $steps = (!empty($howItWorks['steps']) && is_array($howItWorks['steps'])) ? $howItWorks['steps'] : $defaultSteps;
@endphp

<section class="py-24 bg-white">
    <div class="px-4 sm:px-6 lg:px-8 max-w-container mx-auto">
        <h2 class="section-heading text-center mb-16">{{ $howItWorks['title'] ?? 'How it Works' }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-8 relative">
            <div class="hidden md:block absolute top-6 left-[12%] right-[12%] h-px bg-slate-200 -z-0"></div>
            @foreach($steps as $index => $step)
                <div class="relative z-10 text-center space-y-4">
                    <div class="w-12 h-12 bg-brand-primary text-white rounded-full flex items-center justify-center font-bold mx-auto shadow-brand">{{ $index + 1 }}</div>
                    <h4 class="text-lg font-bold text-midnight-deep">{{ $step['title'] ?? '' }}</h4>
                    <p class="text-slate-500 text-sm font-medium">{{ $step['description'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section id="faqs" class="py-24 bg-surface-container-low/60">
    <div class="px-4 sm:px-6 lg:px-8 max-w-container mx-auto grid lg:grid-cols-2 gap-12 lg:gap-20 items-start">
        <div>
            <h2 class="section-heading mb-6">Trusted by Nigerian businesses of all sizes.</h2>
            <p class="text-slate-600 font-medium mb-10 leading-relaxed">
                We are committed to providing reliable payment infrastructure for merchants, developers, and growing teams — in partnership with METRAVON INNOVATION LTD.
            </p>
            @include('partials.faq-section', [
                'category' => 'payment-gateway',
                'title' => '',
                'limit' => 5,
                'showAllLink' => false,
                'sectionId' => 'faq-accordion',
            ])
            <a href="{{ route('faqs.index') }}" class="inline-flex items-center gap-2 mt-6 text-sm font-bold text-brand-primary hover:underline">
                See all FAQs <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>
        <div class="flex items-center justify-center lg:sticky lg:top-28">
            <div class="relative w-full max-w-md">
                <div class="absolute -inset-6 bg-gradient-to-br from-brand-primary/20 to-brand-electric/10 blur-2xl rounded-full opacity-60"></div>
                <img
                    src="{{ MarketingAssets::url('trust-card') }}"
                    alt="CheckoutPay premium card visual"
                    class="relative w-full rounded-2xl shadow-2xl object-cover aspect-video"
                    loading="lazy"
                >
            </div>
        </div>
    </div>
</section>

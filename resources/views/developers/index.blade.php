@extends('layouts.marketing')

@section('title')
    @include('partials.marketing-head', [
        'seoPath' => '/developers',
        'jsonLdExtra' => [\App\Support\FaqCatalog::faqPageJsonLd(array_merge(
            \App\Support\FaqCatalog::forCategory('api'),
            \App\Support\FaqCatalog::forCategory('developer-program')
        ))],
    ])
@endsection

@section('content')
    <x-marketing.product-hero
        badge="Developer hub"
        icon="fa-code"
        title="Developer Hub"
        subtitle="Everything you need to integrate CheckoutPay — API reference, webhooks, testing tools, and the Developer Program."
    >
        <x-slot:actions>
            <a href="{{ route('developers.program') }}" class="btn-brand">
                <i class="fas fa-handshake" aria-hidden="true"></i>
                Developer Program
            </a>
            <a href="{{ route('developers.program.apply') }}" class="btn-brand-outline">
                Apply to the program
            </a>
        </x-slot:actions>
    </x-marketing.product-hero>

    <x-marketing.product-section title="API reference" bg="white">
        <div class="card-marketing p-6 sm:p-8 max-w-4xl mx-auto">
            <h3 class="text-xl font-bold text-midnight-deep mb-4">Base URL</h3>
            <div class="code-block-dark mb-6">
                <code>{{ url('/api/v1') }}</code>
            </div>
            <h3 class="text-xl font-bold text-midnight-deep mb-4">Authentication</h3>
            <p class="text-slate-600 mb-4 font-medium">All API requests require an API key in the header:</p>
            <div class="code-block-dark mb-6">
                <code>X-API-Key: pk_your_api_key_here</code>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('api-docs') }}" class="btn-brand">
                    Full API documentation
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>
                @auth('business')
                    <a href="{{ route('business.api-documentation.index') }}" class="btn-brand-outline">
                        Business dashboard docs
                    </a>
                @endauth
            </div>
        </div>
    </x-marketing.product-section>

    <x-marketing.product-section title="Webhooks" subtitle="Real-time notifications when payment status changes." bg="muted">
        <div class="card-marketing p-6 sm:p-8 max-w-4xl mx-auto">
            <div class="bg-surface-container-low rounded-xl p-4 mb-4">
                <h4 class="font-semibold text-midnight-deep mb-2">Webhook events</h4>
                <ul class="space-y-2 text-sm text-slate-600">
                    <li><code class="code-inline">payment.approved</code> — Payment verified and approved</li>
                    <li><code class="code-inline">payment.rejected</code> — Payment rejected or expired</li>
                </ul>
            </div>
            <a href="{{ route('api-docs') }}#webhooks" class="text-brand-primary hover:text-brand-secondary font-semibold">
                Learn more about webhooks <i class="fas fa-arrow-right ml-1 text-xs"></i>
            </a>
        </div>
    </x-marketing.product-section>

    <x-marketing.product-section title="Quick integration guide" bg="white">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
            @foreach([
                ['step' => '1', 'title' => 'Get API keys', 'desc' => 'Sign up and get your API keys from the dashboard'],
                ['step' => '2', 'title' => 'Make API call', 'desc' => 'Create payment requests using our REST API'],
                ['step' => '3', 'title' => 'Receive webhook', 'desc' => 'Get notified when payment is verified'],
            ] as $item)
                <div class="card-marketing p-6 text-center">
                    <div class="w-10 h-10 bg-brand-primary/10 rounded-xl flex items-center justify-center mb-4 mx-auto">
                        <span class="text-brand-primary font-bold text-xl">{{ $item['step'] }}</span>
                    </div>
                    <h3 class="font-bold text-midnight-deep mb-2">{{ $item['title'] }}</h3>
                    <p class="text-sm text-slate-600">{{ $item['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </x-marketing.product-section>

    <x-marketing.product-section title="Testing" bg="muted">
        <div class="card-marketing p-6 sm:p-8 max-w-4xl mx-auto">
            <h3 class="text-xl font-bold text-midnight-deep mb-4">Test mode</h3>
            <p class="text-slate-600 mb-4 font-medium">Use test API keys to validate your integration without processing real payments.</p>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
                <p class="text-sm text-amber-900"><strong>Note:</strong> Test mode uses the same API endpoints but with test credentials. No real payments will be processed.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('checkout-demo.index') }}" class="btn-brand-outline">Try hosted checkout demo</a>
                <a href="{{ route('business.register') }}" class="btn-brand">Get started with test keys</a>
            </div>
        </div>
    </x-marketing.product-section>

    @include('partials.faq-section', ['categories' => ['api', 'developer-program'], 'title' => 'Developer & API FAQs'])

    <x-marketing.product-cta
        title="Build with CheckoutPay"
        subtitle="Join the Developer Program or create a business account to get API keys."
        :primary-url="route('developers.program.apply')"
        primary-label="Apply to Developer Program"
        :secondary-url="route('business.register')"
        secondary-label="Create business account"
    />
@endsection

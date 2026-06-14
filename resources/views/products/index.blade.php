@extends('layouts.marketing')

@section('title')
    @include('partials.marketing-head', ['seoPath' => '/products'])
@endsection

@section('content')
    <x-marketing.product-hero
        badge="Business solutions"
        title="Business Solutions"
        subtitle="WhatsApp Wallet. Payments. Dollar card. Invoices. Rentals. Tickets. Memberships."
    >
        <x-slot:actions>
            <x-checkoutnow-apk-download label="Download CheckoutNow" class="btn-brand" />
            <a href="{{ \App\Support\CheckoutNowApp::webUrl() }}" target="_blank" rel="noopener noreferrer" class="btn-brand-outline">
                Open web app <i class="fas fa-external-link-alt text-xs" aria-hidden="true"></i>
            </a>
        </x-slot:actions>
    </x-marketing.product-hero>

    <x-marketing.virtual-card-section :virtual-card="$virtualCard ?? []" />
    <x-marketing.whatsapp-wallet-section />
    <x-marketing.commerce-infrastructure />

    <x-marketing.product-section title="Payment integrations" subtitle="Accept NGN payments across channels." bg="muted">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
            <x-marketing.product-card
                href="{{ route('developers.index') }}"
                icon="fa-code"
                title="Payment Gateway API"
                description="RESTful API with webhooks and real-time status tracking."
            >
                <x-slot:bullets>
                    <li class="flex items-start gap-2"><i class="fas fa-check-circle text-brand-primary mt-0.5"></i><span>Comprehensive documentation</span></li>
                    <li class="flex items-start gap-2"><i class="fas fa-check-circle text-brand-primary mt-0.5"></i><span>Webhook notifications</span></li>
                </x-slot:bullets>
                <x-slot:footer>
                    <span class="text-brand-primary font-semibold text-sm group-hover:underline">View API docs <i class="fas fa-arrow-right ml-1 text-xs"></i></span>
                </x-slot:footer>
            </x-marketing.product-card>

            <x-marketing.product-card
                href="{{ route('checkout-demo.index') }}"
                icon="fa-globe"
                title="Hosted Checkout"
                description="Redirect customers to a secure, mobile-optimized payment page."
            >
                <x-slot:bullets>
                    <li class="flex items-start gap-2"><i class="fas fa-check-circle text-brand-primary mt-0.5"></i><span>No coding required</span></li>
                    <li class="flex items-start gap-2"><i class="fas fa-check-circle text-brand-primary mt-0.5"></i><span>Automatic reconciliation</span></li>
                </x-slot:bullets>
                <x-slot:footer>
                    <span class="text-brand-primary font-semibold text-sm group-hover:underline">Try demo <i class="fas fa-arrow-right ml-1 text-xs"></i></span>
                </x-slot:footer>
            </x-marketing.product-card>

            <x-marketing.product-card
                href="{{ route('wordpress-plugin.index') }}"
                icon="fa-wordpress"
                icon-bg="bg-violet-100"
                icon-color="text-violet-600"
                title="WordPress / WooCommerce"
                description="Official plugin for WooCommerce bank-transfer checkout."
            >
                <x-slot:footer>
                    <div class="flex flex-col gap-2">
                        <span class="text-brand-primary font-semibold text-sm group-hover:underline">Plugin page <i class="fas fa-arrow-right ml-1 text-xs"></i></span>
                        <x-checkoutpay-plugin-download :icon="false" class="text-brand-primary font-semibold text-sm hover:underline" />
                    </div>
                </x-slot:footer>
            </x-marketing.product-card>
        </div>
    </x-marketing.product-section>

    <x-marketing.product-section title="Business products" subtitle="Specialized tools for collections, billing, and operations.">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
            @foreach([
                ['route' => 'products.invoices', 'icon' => 'fa-file-invoice', 'bg' => 'bg-emerald-100', 'color' => 'text-emerald-600', 'title' => 'Invoices', 'desc' => 'Professional invoices with payment links and PDF export.'],
                ['route' => 'products.rentals-info', 'icon' => 'fa-box', 'bg' => 'bg-blue-100', 'color' => 'text-blue-600', 'title' => 'Rentals', 'desc' => 'Equipment and property rentals with cart, KYC, and bookings.'],
                ['route' => 'products.memberships-info', 'icon' => 'fa-id-card', 'bg' => 'bg-violet-100', 'color' => 'text-violet-600', 'title' => 'Memberships', 'desc' => 'Subscription memberships with digital cards and QR codes.'],
                ['route' => 'products.tickets-info', 'icon' => 'fa-ticket-alt', 'bg' => 'bg-orange-100', 'color' => 'text-orange-600', 'title' => 'Event Tickets', 'desc' => 'Sell tickets with QR verification and digital delivery.'],
                ['route' => 'payout.index', 'icon' => 'fa-money-bill-wave', 'bg' => 'bg-rose-100', 'color' => 'text-rose-600', 'title' => 'Payout', 'desc' => 'Withdraw earnings to your bank account quickly.'],
                ['route' => 'collections.index', 'icon' => 'fa-wallet', 'bg' => 'bg-indigo-100', 'color' => 'text-indigo-600', 'title' => 'Collections', 'desc' => 'Track balances, history, and payment collections.'],
            ] as $item)
                <x-marketing.product-card
                    href="{{ route($item['route']) }}"
                    :icon="$item['icon']"
                    :icon-bg="$item['bg']"
                    :icon-color="$item['color']"
                    :title="$item['title']"
                    :description="$item['desc']"
                >
                    <x-slot:footer>
                        <span class="text-brand-primary font-semibold text-sm group-hover:underline">View details <i class="fas fa-arrow-right ml-1 text-xs"></i></span>
                    </x-slot:footer>
                </x-marketing.product-card>
            @endforeach
        </div>
    </x-marketing.product-section>

    <x-marketing.woocommerce-section />

    <x-marketing.product-cta
        title="Ready to grow your business?"
        subtitle="Create an account and start accepting payments today."
        :primary-url="route('business.register')"
        primary-label="Create your account"
        :secondary-url="route('pricing')"
        secondary-label="View pricing"
    />
@endsection

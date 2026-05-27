<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CheckoutPay WordPress Plugin — WooCommerce Bank Transfer Gateway</title>
    @include('partials.seo-head', ['seoOverrides' => \App\Support\Seo::forPath('/wordpress-plugin'), 'jsonLdExtra' => [
        \App\Support\Seo::softwareApplicationJsonLd(),
        \App\Support\FaqCatalog::faqPageJsonLd(\App\Support\FaqCatalog::forCategory('wordpress-plugin')),
    ]])
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
@include('partials.tailwind-assets')
</head>
<body class="bg-white">
    @include('partials.nav')

    <section class="bg-gradient-to-br from-purple-50 via-white to-primary/5 py-12 sm:py-16 md:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <div class="inline-flex items-center bg-purple-100 text-purple-800 px-3 py-1.5 rounded-full text-xs sm:text-sm font-medium mb-4">
                    <i class="fab fa-wordpress mr-2"></i> Official WooCommerce extension
                </div>
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                    CheckoutPay – Bank Transfer Gateway for WooCommerce
                </h1>
                <p class="text-base sm:text-lg text-gray-600 mb-6">
                    Accept Nigerian bank-transfer payments in WooCommerce. Customers pay to a virtual account; orders update automatically when CheckoutPay confirms the transfer.
                </p>
                <p class="text-sm text-gray-500 mb-8">{{ \App\Support\CheckoutPayWordPressPlugin::versionLine() }}</p>
                <p class="text-sm text-gray-500 mb-4">
                    Agencies: earn revenue share — <a href="{{ route('developers.program') }}" class="text-primary font-medium hover:underline">Developer Program</a>
                    · <a href="{{ route('faqs.index') }}#wordpress-plugin" class="text-primary font-medium hover:underline">Plugin FAQs</a>
                </p>
                <div class="flex flex-col sm:flex-row gap-3">
                    <x-checkoutpay-plugin-download label="Download plugin" class="inline-flex items-center justify-center px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium shadow-lg" />
                    <a href="{{ route('business.register') }}" class="inline-flex items-center justify-center px-6 py-3 bg-white text-purple-700 border-2 border-purple-200 rounded-lg hover:bg-purple-50 font-medium">
                        Get API key
                    </a>
                    <a href="{{ route('support.index') }}" class="inline-flex items-center justify-center px-6 py-3 text-gray-700 hover:text-primary font-medium">
                        Support
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="py-12 bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Features</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="p-5 rounded-xl border border-gray-200">
                    <i class="fas fa-university text-primary mb-3"></i>
                    <h3 class="font-semibold text-gray-900 mb-2">Bank transfer checkout</h3>
                    <p class="text-sm text-gray-600">Virtual account details on the order thank-you page.</p>
                </div>
                <div class="p-5 rounded-xl border border-gray-200">
                    <i class="fas fa-bolt text-primary mb-3"></i>
                    <h3 class="font-semibold text-gray-900 mb-2">Webhooks &amp; status checks</h3>
                    <p class="text-sm text-gray-600">Orders move to paid when transfers are matched.</p>
                </div>
                <div class="p-5 rounded-xl border border-gray-200">
                    <i class="fas fa-cubes text-primary mb-3"></i>
                    <h3 class="font-semibold text-gray-900 mb-2">Blocks &amp; HPOS</h3>
                    <p class="text-sm text-gray-600">Works with Cart/Checkout blocks and WooCommerce HPOS.</p>
                </div>
                <div class="p-5 rounded-xl border border-gray-200">
                    <i class="fas fa-flask text-primary mb-3"></i>
                    <h3 class="font-semibold text-gray-900 mb-2">Test mode</h3>
                    <p class="text-sm text-gray-600">Test checkout before going live.</p>
                </div>
                <div class="p-5 rounded-xl border border-gray-200">
                    <i class="fas fa-percent text-primary mb-3"></i>
                    <h3 class="font-semibold text-gray-900 mb-2">Fee preview</h3>
                    <p class="text-sm text-gray-600">Refresh charges from CheckoutPay in gateway settings.</p>
                </div>
                <div class="p-5 rounded-xl border border-gray-200">
                    <i class="fas fa-store text-primary mb-3"></i>
                    <h3 class="font-semibold text-gray-900 mb-2">No storefront promos</h3>
                    <p class="text-sm text-gray-600">CheckoutPay links appear only in admin settings.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-12 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-8">Installation</h2>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-purple-100 text-purple-800 font-bold text-sm mb-4">1</span>
                    <h3 class="font-semibold text-gray-900 mb-2">Install in WordPress</h3>
                    <ol class="text-sm text-gray-600 space-y-2 list-decimal list-inside">
                        <li>Upload and activate the plugin (WooCommerce required).</li>
                        <li>Go to <strong>WooCommerce → Settings → Payments</strong>.</li>
                        <li>Enable <strong>CheckoutPay</strong> and open <strong>Manage</strong>.</li>
                    </ol>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-purple-100 text-purple-800 font-bold text-sm mb-4">2</span>
                    <h3 class="font-semibold text-gray-900 mb-2">Connect CheckoutPay</h3>
                    <ol class="text-sm text-gray-600 space-y-2 list-decimal list-inside">
                        <li>Log in at <a href="{{ url('/') }}" class="text-primary hover:underline">check-outpay.com</a> and copy your API key.</li>
                        <li>Set API URL to <code class="bg-gray-100 px-1 rounded text-xs">https://check-outpay.com/api/v1</code>.</li>
                        <li>Register your store <strong>website URL</strong> in CheckoutPay → Websites.</li>
                        <li>Paste the plugin <strong>webhook URL</strong> into CheckoutPay.</li>
                        <li>Click <strong>Refresh charges</strong> to verify the connection.</li>
                    </ol>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-purple-100 text-purple-800 font-bold text-sm mb-4">3</span>
                    <h3 class="font-semibold text-gray-900 mb-2">Test &amp; go live</h3>
                    <ol class="text-sm text-gray-600 space-y-2 list-decimal list-inside">
                        <li>Place a test order and confirm bank details on the thank-you page.</li>
                        <li>Confirm the order status updates when payment is approved.</li>
                        <li>Disable test mode for live sales.</li>
                    </ol>
                </div>
            </div>
            <p class="mt-8 text-sm text-gray-500">
                Webhook format: <code class="bg-gray-100 px-1 rounded">https://your-store.com/?wc-api=wc_checkoutpay_webhook</code>
            </p>
        </div>
    </section>

    <section class="py-12 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Requirements</h2>
            <ul class="text-gray-600 space-y-2">
                <li class="flex items-center gap-2"><i class="fas fa-check text-green-500"></i> {{ \App\Support\CheckoutPayWordPressPlugin::requirementsLabel() }}</li>
                <li class="flex items-center gap-2"><i class="fas fa-check text-green-500"></i> CheckoutPay merchant account with API key</li>
                <li class="flex items-center gap-2"><i class="fas fa-check text-green-500"></i> HTTPS recommended for webhooks</li>
            </ul>
        </div>
    </section>

    @include('partials.faq-section', [
        'category' => 'wordpress-plugin',
        'title' => 'WordPress & WooCommerce FAQs',
        'showAllLink' => true,
    ])

    <section class="py-12 bg-purple-600 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-2xl font-bold mb-4">Ready to install?</h2>
            <p class="text-purple-100 mb-6 max-w-xl mx-auto">Download the official plugin ZIP and connect your store in minutes.</p>
            <x-checkoutpay-plugin-download label="Download {{ \App\Support\CheckoutPayWordPressPlugin::version() }}" class="inline-flex items-center justify-center px-8 py-3 bg-white text-purple-700 rounded-lg hover:bg-purple-50 font-semibold shadow-lg" />
        </div>
    </section>

    @include('partials.footer')
</body>
</html>

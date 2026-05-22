<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support — CheckoutPay Payment Gateway Nigeria</title>
    @include('partials.seo-head', ['seoOverrides' => [
        'title' => 'Support — CheckoutPay Payment Gateway Nigeria',
        'description' => 'Get help with CheckoutPay — Nigeria\'s affordable, reliable payment gateway. Contact support for payments, WooCommerce, API, and wallet issues.',
        'path' => '/support',
    ]])
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#3C50E0' },
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-white">
    @include('partials.nav')

    <section class="bg-gradient-to-br from-primary/10 via-white to-primary/5 py-12 sm:py-16 md:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-gray-900 mb-4">Support Center</h1>
            <p class="text-base sm:text-lg text-gray-600 mb-4 max-w-2xl mx-auto">
                Live help from our team, product guides, and answers — all in one place.
            </p>
            <p class="text-sm text-gray-500 mb-8 max-w-2xl mx-auto">
                For payment problems, enter the <strong>session ID from your bank transfer</strong> (receipt or transfer details) and the amount you sent — then choose WhatsApp or in-browser chat. Linking WhatsApp is recommended so we can update you faster.
            </p>
            <button type="button" id="cp-support-hero-open" data-cp-support-open
                class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white font-semibold rounded-lg hover:bg-primary/90 shadow-lg">
                <i class="fas fa-comments"></i> Chat with us
            </button>
        </div>
    </section>

    <section class="py-12 bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-2 text-center">Our products</h2>
            <p class="text-gray-600 text-center mb-10">Payments, invoices, rentals, and more.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <a href="{{ route('products.index') }}#api" class="block bg-white rounded-xl shadow border border-gray-200 p-6 hover:border-primary/40 transition">
                    <i class="fas fa-code text-primary text-2xl mb-3"></i>
                    <h3 class="font-bold text-gray-900">Payment Gateway API</h3>
                    <p class="text-sm text-gray-600 mt-2">REST API with webhooks for WooCommerce and custom sites.</p>
                </a>
                <a href="{{ route('products.invoices') }}" class="block bg-white rounded-xl shadow border border-gray-200 p-6 hover:border-primary/40 transition">
                    <i class="fas fa-file-invoice text-primary text-2xl mb-3"></i>
                    <h3 class="font-bold text-gray-900">Invoices</h3>
                    <p class="text-sm text-gray-600 mt-2">Send payment links and track collections.</p>
                </a>
                <a href="{{ route('rentals.index') }}" class="block bg-white rounded-xl shadow border border-gray-200 p-6 hover:border-primary/40 transition">
                    <i class="fas fa-home text-primary text-2xl mb-3"></i>
                    <h3 class="font-bold text-gray-900">Rentals</h3>
                    <p class="text-sm text-gray-600 mt-2">Property listings and booking payments.</p>
                </a>
                <a href="{{ route('collections.index') }}" class="block bg-white rounded-xl shadow border border-gray-200 p-6 hover:border-primary/40 transition">
                    <i class="fas fa-hand-holding-usd text-primary text-2xl mb-3"></i>
                    <h3 class="font-bold text-gray-900">Collections</h3>
                    <p class="text-sm text-gray-600 mt-2">Group payments and shared contribution links.</p>
                </a>
                <a href="{{ route('checkout-demo.index') }}" class="block bg-white rounded-xl shadow border border-gray-200 p-6 hover:border-primary/40 transition">
                    <i class="fas fa-shopping-cart text-primary text-2xl mb-3"></i>
                    <h3 class="font-bold text-gray-900">Checkout demo</h3>
                    <p class="text-sm text-gray-600 mt-2">See the customer payment experience.</p>
                </a>
                <div class="bg-primary/5 rounded-xl border border-primary/20 p-6">
                    <i class="fab fa-whatsapp text-green-600 text-2xl mb-3"></i>
                    <h3 class="font-bold text-gray-900">WhatsApp wallet</h3>
                    <p class="text-sm text-gray-600 mt-2">Support chats link to your WhatsApp wallet. Approved refunds can be credited there and transferred to any Nigerian bank.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-12 sm:py-16 bg-gray-50">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Before you chat</h2>
            <ul class="space-y-3 text-sm text-gray-700 list-disc pl-5">
                <li>We ask for your <strong>WhatsApp number</strong> to save and track your support conversation.</li>
                <li>Your number creates or links a <strong>CheckoutPay WhatsApp wallet</strong> (same as CheckoutNow).</li>
                <li>You will get a <strong>message on WhatsApp</strong> when support starts.</li>
                <li><strong>Refunds</strong>, when approved, may be sent to that wallet; you can transfer out to any bank you choose.</li>
            </ul>
        </div>
    </section>

    <section class="py-12 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Common questions</h2>
            <div class="grid md:grid-cols-2 gap-6 text-sm text-gray-600">
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">How do I get started?</h4>
                    <p>Sign up, verify your business, and get API keys from the dashboard.</p>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">WooCommerce plugin?</h4>
                    <p>Download the latest CheckoutPay gateway from our integrations page.</p>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">Charges?</h4>
                    <p>Default 1% + ₦100 per transaction; configurable per website.</p>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">Still need email?</h4>
                    <p><a href="{{ route('contact') }}" class="text-primary font-medium">Contact form</a> for non-urgent requests.</p>
                </div>
            </div>
        </div>
    </section>

    @include('partials.footer')
</body>
</html>

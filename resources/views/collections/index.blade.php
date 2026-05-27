<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.marketing-head', [
        'seoPath' => '/collections',
        'jsonLdExtra' => [\App\Support\FaqCatalog::faqPageJsonLd(\App\Support\FaqCatalog::forCategory('payouts-collections'))],
    ])
@include('partials.tailwind-assets')
</head>
<body class="bg-white">
    @include('partials.nav')
    <section class="py-12 sm:py-16 md:py-20 bg-gradient-to-br from-primary/10 via-white to-primary/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-6 text-center">Payment Collections</h1>
            <p class="text-center text-gray-600 mb-8">Accept payments from customers through multiple channels.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-3">API Integration</h3>
                    <p class="text-gray-600">Integrate payments directly into your application.</p>
                </div>
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-3">Hosted Checkout</h3>
                    <p class="text-gray-600">Redirect customers to our secure payment page.</p>
                </div>
            </div>
        </div>
    </section>
    @include('partials.faq-section', ['category' => 'payouts-collections', 'title' => 'Collections & payouts FAQs'])
    @include('partials.footer')
</body>
</html>

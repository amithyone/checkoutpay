<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
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

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-primary/10 via-white to-primary/5 py-12 sm:py-16 md:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-gray-900 mb-4 sm:mb-6">
                    Payment Solutions for Every Business
                </h1>
                <p class="text-base sm:text-lg md:text-xl text-gray-600 mb-6 sm:mb-8 max-w-3xl mx-auto">
                    Accept payments seamlessly with our comprehensive payment gateway solutions. From API integration to hosted checkout pages, we have everything you need.
                </p>
            </div>
        </div>
    </section>

    <!-- Products Grid -->
    <section class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">
                <!-- Payment Gateway API -->
                <div id="api" class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-code text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">Payment Gateway API</h3>
                    <p class="text-gray-600 mb-4">
                        Integrate payments directly into your application with our RESTful API. Full control over the payment experience with real-time webhooks and transaction status checks.
                    </p>
                    <ul class="space-y-2 mb-6 text-sm text-gray-600">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>RESTful API with comprehensive documentation</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Real-time webhook notifications</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Transaction status tracking</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Charge management (customer or business pays)</span>
                        </li>
                    </ul>
                    <a href="{{ route('developers.index') }}" class="inline-flex items-center text-primary hover:text-primary/80 font-medium">
                        View API Documentation
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>

                <!-- Hosted Checkout -->
                <div id="hosted-checkout" class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-globe text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">Hosted Checkout Page</h3>
                    <p class="text-gray-600 mb-4">
                        Redirect customers to our secure hosted payment page. No integration required - just redirect and we handle the rest. Perfect for quick setup.
                    </p>
                    <ul class="space-y-2 mb-6 text-sm text-gray-600">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>No coding required - simple redirect</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Secure payment processing</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Automatic redirect after payment</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Mobile-optimized checkout</span>
                        </li>
                    </ul>
                    <a href="{{ route('resources.index') }}#hosted-checkout" class="inline-flex items-center text-primary hover:text-primary/80 font-medium">
                        Learn More
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>

                <!-- WordPress Plugin -->
                <div id="wordpress-plugin" class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fab fa-wordpress text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">WordPress / WooCommerce Plugin</h3>
                    <p class="text-gray-600 mb-4">
                        Quick integration for WooCommerce stores. Install our plugin and start accepting payments in minutes. Works seamlessly with your existing store.
                    </p>
                    <ul class="space-y-2 mb-6 text-sm text-gray-600">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>One-click installation</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Automatic charge calculation</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Customer or business pays charges</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Compatible with WordPress 5.0+ and WooCommerce 5.0+</span>
                        </li>
                    </ul>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="{{ asset('downloads/checkoutpay-gateway.zip') }}" download class="inline-flex items-center justify-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium">
                            <i class="fas fa-download mr-2"></i> Download Plugin
                        </a>
                        <a href="{{ route('resources.index') }}#wordpress" class="inline-flex items-center justify-center px-4 py-2 border-2 border-purple-600 text-purple-600 rounded-lg hover:bg-purple-50 font-medium">
                            Installation Guide
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-12 sm:py-16 md:py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-4">Why Choose CheckoutPay?</h2>
                <p class="text-lg text-gray-600">Powerful features that make payment processing simple</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
                <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-bolt text-primary text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Fast Integration</h3>
                    <p class="text-gray-600 text-sm">Get up and running in minutes with our simple API or hosted checkout page.</p>
                </div>
                <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-shield-alt text-primary text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Secure & Reliable</h3>
                    <p class="text-gray-600 text-sm">Bank-level security with automatic payment verification and fraud protection.</p>
                </div>
                <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-chart-line text-primary text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Transparent Pricing</h3>
                    <p class="text-gray-600 text-sm">Simple, transparent pricing with no hidden fees. Just 1% + ₦100 per transaction.</p>
                </div>
                <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-mobile-alt text-primary text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Mobile Optimized</h3>
                    <p class="text-gray-600 text-sm">All our payment solutions are fully optimized for mobile devices.</p>
                </div>
                <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-headset text-primary text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">24/7 Support</h3>
                    <p class="text-gray-600 text-sm">Get help when you need it with our dedicated support team.</p>
                </div>
                <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-cog text-primary text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Flexible Charges</h3>
                    <p class="text-gray-600 text-sm">Choose whether you or your customers pay transaction fees.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-12 sm:py-16 md:py-20 bg-gradient-to-r from-primary to-primary/90">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-4">Ready to Get Started?</h2>
            <p class="text-lg md:text-xl text-primary-100 mb-8">Join thousands of businesses using CheckoutPay to accept payments</p>
            <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                <a href="{{ route('business.register') }}" class="w-full sm:w-auto bg-white text-primary px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-gray-100 font-medium text-base sm:text-lg transition-colors shadow-lg">
                    Create Your Account
                </a>
                <a href="{{ route('pricing') }}" class="w-full sm:w-auto bg-transparent text-white border-2 border-white px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-white/10 font-medium text-base sm:text-lg transition-colors">
                    View Pricing
                </a>
            </div>
        </div>
    </section>

    @include('partials.footer')
</body>
</html>

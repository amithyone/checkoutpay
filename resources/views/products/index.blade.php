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
                    Business Solutions
                </h1>
                <p class="text-base sm:text-lg md:text-xl text-gray-600 mb-6 sm:mb-8 max-w-3xl mx-auto">
                    Payments. Invoices. Rentals. Tickets. Memberships.
                </p>
            </div>
        </div>
    </section>

    <!-- Core Payment Products -->
    <section class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-4">Payment Solutions</h2>
                <p class="text-lg text-gray-600">Accept payments in NGN.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                <!-- Payment Gateway API -->
                <div id="api" class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-code text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">Payment Gateway API</h3>
                    <p class="text-gray-600 mb-4">
                        RESTful API for payment integration. Real-time webhooks included.
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
                    </ul>
                    <a href="{{ route('developers.index') }}" class="inline-flex items-center text-primary hover:text-primary/80 font-medium">
                        View API Documentation <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>

                <!-- Hosted Checkout -->
                <div id="hosted-checkout" class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-globe text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">Hosted Checkout Page</h3>
                    <p class="text-gray-600 mb-4">
                        Redirect to hosted payment page. No integration required.
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
                            <span>Mobile-optimized checkout</span>
                        </li>
                    </ul>
                    <a href="{{ route('checkout-demo.index') }}" class="inline-flex items-center text-primary hover:text-primary/80 font-medium">
                        Try Demo <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>

                <!-- WordPress Plugin -->
                <div id="wordpress-plugin" class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fab fa-wordpress text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">WordPress / WooCommerce</h3>
                    <p class="text-gray-600 mb-4">
                        Plugin for WooCommerce stores. Install and configure.
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
                            <span>Compatible with WordPress 5.0+</span>
                        </li>
                    </ul>
                    <a href="{{ asset('downloads/checkoutpay-gateway.zip') }}" download class="inline-flex items-center text-primary hover:text-primary/80 font-medium">
                        Download Plugin <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Business Products -->
    <section class="py-12 sm:py-16 md:py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-4">Business Products</h2>
                <p class="text-lg text-gray-600">Available services.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                <!-- Invoices -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-file-invoice text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">Invoices</h3>
                    <p class="text-gray-600 mb-4">
                        Create invoices with payment links. PDF export included.
                    </p>
                    <ul class="space-y-2 mb-6 text-sm text-gray-600">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Professional invoice templates</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Integrated payment links</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>PDF export & email sending</span>
                        </li>
                    </ul>
                    <a href="{{ route('products.invoices') }}" class="inline-flex items-center text-primary hover:text-primary/80 font-medium">
                        View Details <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>

                <!-- Rentals -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-box text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">Rentals</h3>
                    <p class="text-gray-600 mb-4">
                        Rent equipment, vehicles, properties. Manage availability and bookings.
                    </p>
                    <ul class="space-y-2 mb-6 text-sm text-gray-600">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Category & city-based filtering</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Cart system for multiple items</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>KYC verification & secure payments</span>
                        </li>
                    </ul>
                    <a href="{{ route('products.rentals-info') }}" class="inline-flex items-center text-primary hover:text-primary/80 font-medium">
                        View Details <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>

                <!-- Memberships -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-id-card text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">Memberships</h3>
                    <p class="text-gray-600 mb-4">
                        Subscription memberships with digital cards. QR codes included.
                    </p>
                    <ul class="space-y-2 mb-6 text-sm text-gray-600">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Digital membership cards with QR codes</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Global or location-based memberships</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Member tracking & capacity management</span>
                        </li>
                    </ul>
                    <a href="{{ route('products.memberships-info') }}" class="inline-flex items-center text-primary hover:text-primary/80 font-medium">
                        View Details <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>

                <!-- Tickets -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-ticket-alt text-orange-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">Event Tickets</h3>
                    <p class="text-gray-600 mb-4">
                        Sell tickets for events. QR code verification. Digital delivery.
                    </p>
                    <ul class="space-y-2 mb-6 text-sm text-gray-600">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Multiple ticket types & pricing tiers</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>QR code verification & mobile scanner</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Digital PDF tickets via email</span>
                        </li>
                    </ul>
                    <a href="{{ route('products.tickets-info') }}" class="inline-flex items-center text-primary hover:text-primary/80 font-medium">
                        View Details <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>

                <!-- Payout -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-money-bill-wave text-red-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">Payout</h3>
                    <p class="text-gray-600 mb-4">
                        Withdraw earnings to bank account. Transaction history available.
                    </p>
                    <ul class="space-y-2 mb-6 text-sm text-gray-600">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Fast bank transfers</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Account verification & management</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Transaction history & tracking</span>
                        </li>
                    </ul>
                    <a href="{{ route('payout.index') }}" class="inline-flex items-center text-primary hover:text-primary/80 font-medium">
                        View Details <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>

                <!-- Collections -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-wallet text-indigo-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">Collections</h3>
                    <p class="text-gray-600 mb-4">
                        Track payment collections. View balances and transaction history.
                    </p>
                    <ul class="space-y-2 mb-6 text-sm text-gray-600">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Real-time balance tracking</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Transaction history & reports</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2 flex-shrink-0"></i>
                            <span>Analytics & insights</span>
                        </li>
                    </ul>
                    <a href="{{ route('collections.index') }}" class="inline-flex items-center text-primary hover:text-primary/80 font-medium">
                        View Details <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-4">How It Works</h2>
                <p class="text-lg text-gray-600">Process payments.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
                <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-bolt text-primary text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Integration</h3>
                    <p class="text-gray-600 text-sm">API or hosted checkout page.</p>
                </div>
                <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-shield-alt text-primary text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Security</h3>
                    <p class="text-gray-600 text-sm">Automatic payment verification.</p>
                </div>
                <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-chart-line text-primary text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Rates</h3>
                    <p class="text-gray-600 text-sm">Competitive rates. 1% + ₦50 per transaction.</p>
                </div>
                <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-mobile-alt text-primary text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Mobile</h3>
                    <p class="text-gray-600 text-sm">Optimized for mobile devices.</p>
                </div>
                <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-headset text-primary text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Support</h3>
                    <p class="text-gray-600 text-sm">Documentation available.</p>
                </div>
                <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-cog text-primary text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Charges</h3>
                    <p class="text-gray-600 text-sm">Choose who pays transaction fees.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-12 sm:py-16 md:py-20 bg-gradient-to-r from-primary to-primary/90">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-4">Get Started</h2>
            <p class="text-lg md:text-xl text-primary-100 mb-8">Create an account.</p>
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

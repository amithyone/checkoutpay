<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CheckoutPay - Intelligent Payment Gateway</title>
    <meta name="description" content="Intelligent payment gateway for businesses. Accept payments with the cheapest rates in the market - just 1% + ₦50 per transaction.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#3C50E0',
                        },
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-white">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="h-10 w-10 bg-primary rounded-lg flex items-center justify-center">
                            <i class="fas fa-shield-alt text-white text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-3">
                        <h1 class="text-xl font-bold text-gray-900">CheckoutPay</h1>
                        <p class="text-xs text-gray-500">Intelligent Payment Gateway</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                <a href="{{ route('pricing') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">
                    Pricing
                </a>
                    <a href="{{ route('business.login') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">
                        Login
                    </a>
                    <a href="{{ route('business.register') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 text-sm font-medium transition-colors">
                        Get Started
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-primary/10 via-white to-primary/5 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <div class="inline-block bg-green-100 text-green-800 px-4 py-2 rounded-full text-sm font-medium mb-6">
                    <i class="fas fa-tag mr-2"></i> Cheapest Rates in the Market
                </div>
                <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-6">
                    Intelligent Payment Gateway<br>
                    <span class="text-primary">For Your Business</span>
                </h1>
                <p class="text-xl text-gray-600 mb-4 max-w-3xl mx-auto">
                    Accept payments instantly with the most affordable rates. 
                    Fast, secure, and intelligent payment processing.
                </p>
                <p class="text-2xl font-bold text-primary mb-8">
                    Just 1% + ₦50 per transaction
                </p>
                <div class="flex justify-center space-x-4">
                    <a href="{{ route('business.register') }}" class="bg-primary text-white px-8 py-4 rounded-lg hover:bg-primary/90 font-medium text-lg transition-colors shadow-lg">
                        <i class="fas fa-rocket mr-2"></i> Get Started Free
                    </a>
                    <a href="{{ route('pricing') }}" class="bg-white text-primary border-2 border-primary px-8 py-4 rounded-lg hover:bg-primary/5 font-medium text-lg transition-colors">
                        View Pricing
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Why Choose CheckoutPay?</h2>
                <p class="text-lg text-gray-600">Intelligent payment processing designed for modern businesses</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="bg-white p-8 rounded-lg border border-gray-200 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-brain text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Intelligent Processing</h3>
                    <p class="text-gray-600">
                        Smart payment processing with automatic reconciliation. 
                        Reduce manual work and focus on growing your business.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white p-8 rounded-lg border border-gray-200 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-money-bill-wave text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Lowest Rates</h3>
                    <p class="text-gray-600">
                        The cheapest payment gateway in the market. 
                        Just 1% + ₦50 per transaction. No hidden fees.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white p-8 rounded-lg border border-gray-200 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-bolt text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Fast Integration</h3>
                    <p class="text-gray-600">
                        Get started in minutes with our simple API or hosted checkout page. 
                        No complex setup required.
                    </p>
                </div>

                <!-- Feature 4 -->
                <div class="bg-white p-8 rounded-lg border border-gray-200 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-shield-alt text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Secure & Reliable</h3>
                    <p class="text-gray-600">
                        Bank-level security with encrypted transactions. 
                        Trusted by businesses across Nigeria. Intelligent fraud detection.
                    </p>
                </div>

                <!-- Feature 5 -->
                <div class="bg-white p-8 rounded-lg border border-gray-200 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-bell text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Instant Notifications</h3>
                    <p class="text-gray-600">
                        Get instant webhook notifications for every transaction. 
                        Stay updated in real-time with intelligent alerts.
                    </p>
                </div>

                <!-- Feature 6 -->
                <div class="bg-white p-8 rounded-lg border border-gray-200 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-chart-line text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Dashboard & Analytics</h3>
                    <p class="text-gray-600">
                        Comprehensive dashboard with transaction history, 
                        statistics, and withdrawal management.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Simple, Transparent Pricing</h2>
                <p class="text-lg text-gray-600">The cheapest rates in the market. No hidden fees, no surprises.</p>
            </div>

            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-2xl shadow-xl border-2 border-primary p-8 md:p-12">
                    <div class="text-center mb-8">
                        <div class="inline-block bg-green-100 text-green-800 px-4 py-2 rounded-full text-sm font-medium mb-4">
                            <i class="fas fa-check-circle mr-2"></i> Best Value
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-4">Pay As You Go</h3>
                        <div class="mb-6">
                            <span class="text-5xl font-bold text-primary">1%</span>
                            <span class="text-2xl text-gray-600"> + </span>
                            <span class="text-5xl font-bold text-primary">₦50</span>
                            <p class="text-gray-600 mt-2">per successful transaction</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        <div class="space-y-4">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">What's Included:</h4>
                            <div class="space-y-3">
                                <div class="flex items-start">
                                    <i class="fas fa-check-circle text-green-600 mt-1 mr-3"></i>
                                    <span class="text-gray-700">Unlimited transactions</span>
                                </div>
                                <div class="flex items-start">
                                    <i class="fas fa-check-circle text-green-600 mt-1 mr-3"></i>
                                    <span class="text-gray-700">API access & documentation</span>
                                </div>
                                <div class="flex items-start">
                                    <i class="fas fa-check-circle text-green-600 mt-1 mr-3"></i>
                                    <span class="text-gray-700">Hosted checkout page</span>
                                </div>
                                <div class="flex items-start">
                                    <i class="fas fa-check-circle text-green-600 mt-1 mr-3"></i>
                                    <span class="text-gray-700">Real-time webhook notifications</span>
                                </div>
                                <div class="flex items-start">
                                    <i class="fas fa-check-circle text-green-600 mt-1 mr-3"></i>
                                    <span class="text-gray-700">Dashboard & analytics</span>
                                </div>
                                <div class="flex items-start">
                                    <i class="fas fa-check-circle text-green-600 mt-1 mr-3"></i>
                                    <span class="text-gray-700">24/7 support</span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-6">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">Pricing Examples:</h4>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-700">₦1,000 transaction</span>
                                    <span class="font-bold text-gray-900">₦60</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-700">₦5,000 transaction</span>
                                    <span class="font-bold text-gray-900">₦100</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-700">₦10,000 transaction</span>
                                    <span class="font-bold text-gray-900">₦150</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-700">₦50,000 transaction</span>
                                    <span class="font-bold text-gray-900">₦550</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-700">₦100,000 transaction</span>
                                    <span class="font-bold text-gray-900">₦1,050</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center pt-8 border-t border-gray-200">
                        <a href="{{ route('business.register') }}" class="bg-primary text-white px-8 py-4 rounded-lg hover:bg-primary/90 font-medium text-lg transition-colors shadow-lg inline-block">
                            Get Started Now
                        </a>
                        <p class="text-sm text-gray-500 mt-4">No setup fees. No monthly fees. Pay only for successful transactions.</p>
                    </div>
                </div>

                <!-- Comparison Badge -->
                <div class="mt-8 text-center">
                    <div class="inline-flex items-center bg-yellow-50 border border-yellow-200 rounded-lg px-6 py-3">
                        <i class="fas fa-trophy text-yellow-600 mr-3"></i>
                        <span class="text-gray-800 font-medium">Cheapest payment gateway rates in Nigeria</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">How It Works</h2>
                <p class="text-lg text-gray-600">Get started in 4 simple steps</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="text-center">
                    <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">
                        1
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Register</h3>
                    <p class="text-gray-600">Create your business account in minutes</p>
                </div>

                <div class="text-center">
                    <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">
                        2
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Integrate</h3>
                    <p class="text-gray-600">Use our API or hosted checkout page</p>
                </div>

                <div class="text-center">
                    <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">
                        3
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Accept Payments</h3>
                    <p class="text-gray-600">Customers pay via bank transfer</p>
                </div>

                <div class="text-center">
                    <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">
                        4
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Get Paid</h3>
                    <p class="text-gray-600">Instant verification and notifications</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-gradient-to-r from-primary to-primary/90">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">Ready to Get Started?</h2>
            <p class="text-xl text-primary-100 mb-8">
                Join businesses using CheckoutPay - the intelligent payment gateway with the cheapest rates
            </p>
            <div class="flex justify-center space-x-4">
                <a href="{{ route('business.register') }}" class="bg-white text-primary px-8 py-4 rounded-lg hover:bg-gray-100 font-medium text-lg transition-colors shadow-lg">
                    Create Your Account
                </a>
                <a href="{{ route('pricing') }}" class="bg-transparent text-white border-2 border-white px-8 py-4 rounded-lg hover:bg-white/10 font-medium text-lg transition-colors">
                    View Pricing
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center mb-4">
                        <div class="h-8 w-8 bg-primary rounded-lg flex items-center justify-center mr-2">
                            <i class="fas fa-shield-alt text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-lg">CheckoutPay</h3>
                            <p class="text-xs text-gray-400">Intelligent Payment Gateway</p>
                        </div>
                    </div>
                    <p class="text-sm">The cheapest payment gateway in the market. Just 1% + ₦50 per transaction.</p>
                </div>

                <div>
                    <h4 class="text-white font-semibold mb-4">Product</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#features" class="hover:text-white">Features</a></li>
                        <li><a href="{{ route('pricing') }}" class="hover:text-white">Pricing</a></li>
                        <li><a href="#how-it-works" class="hover:text-white">How It Works</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-white font-semibold mb-4">Developers</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="https://github.com/amithyone/checkoutpay/blob/main/API_DOCUMENTATION.md" target="_blank" class="hover:text-white">API Documentation</a></li>
                        <li><a href="/api/health" class="hover:text-white">API Status</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-white font-semibold mb-4">Account</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('business.login') }}" class="hover:text-white">Login</a></li>
                        <li><a href="{{ route('business.register') }}" class="hover:text-white">Sign Up</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-800 mt-8 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                    <div class="flex flex-wrap justify-center md:justify-start gap-6 text-sm">
                        <a href="{{ route('privacy-policy') }}" class="text-gray-400 hover:text-white transition-colors">Privacy Policy</a>
                        <a href="{{ route('terms') }}" class="text-gray-400 hover:text-white transition-colors">Terms & Conditions</a>
                        <a href="{{ route('contact') }}" class="text-gray-400 hover:text-white transition-colors">Contact Us</a>
                    </div>
                    <p class="text-sm text-gray-400">&copy; {{ date('Y') }} CheckoutPay. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CheckoutPay - Payment Gateway Solution</title>
    <meta name="description" content="Simple, secure payment gateway for businesses. Accept payments via bank transfers with automatic verification.">
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
    <nav class="bg-white shadow-sm border-b border-gray-200">
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
                    </div>
                </div>
                <div class="flex items-center space-x-4">
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
                <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
                    Simple Payment Gateway<br>
                    <span class="text-primary">For Your Business</span>
                </h1>
                <p class="text-xl text-gray-600 mb-8 max-w-3xl mx-auto">
                    Accept payments via bank transfers with automatic verification. 
                    Fast, secure, and easy to integrate.
                </p>
                <div class="flex justify-center space-x-4">
                    <a href="{{ route('business.register') }}" class="bg-primary text-white px-8 py-3 rounded-lg hover:bg-primary/90 font-medium text-lg transition-colors shadow-lg">
                        <i class="fas fa-rocket mr-2"></i> Get Started Free
                    </a>
                    <a href="#features" class="bg-white text-primary border-2 border-primary px-8 py-3 rounded-lg hover:bg-primary/5 font-medium text-lg transition-colors">
                        Learn More
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
                <p class="text-lg text-gray-600">Everything you need to accept payments online</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
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

                <!-- Feature 2 -->
                <div class="bg-white p-8 rounded-lg border border-gray-200 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-shield-alt text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Secure & Reliable</h3>
                    <p class="text-gray-600">
                        Automatic payment verification via email monitoring. 
                        Secure API authentication and encrypted data.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white p-8 rounded-lg border border-gray-200 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-bell text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Real-time Notifications</h3>
                    <p class="text-gray-600">
                        Instant webhook notifications when payments are verified. 
                        Stay updated with every transaction.
                    </p>
                </div>

                <!-- Feature 4 -->
                <div class="bg-white p-8 rounded-lg border border-gray-200 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-code text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Developer Friendly</h3>
                    <p class="text-gray-600">
                        Clean REST API with comprehensive documentation. 
                        Support for multiple programming languages.
                    </p>
                </div>

                <!-- Feature 5 -->
                <div class="bg-white p-8 rounded-lg border border-gray-200 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-palette text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Flexible Options</h3>
                    <p class="text-gray-600">
                        Choose between API integration or hosted checkout page. 
                        Customize to match your brand.
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

    <!-- How It Works -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">How It Works</h2>
                <p class="text-lg text-gray-600">Simple payment process in 4 easy steps</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="text-center">
                    <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">
                        1
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Register</h3>
                    <p class="text-gray-600">Create your business account and get approved</p>
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
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Receive Payments</h3>
                    <p class="text-gray-600">Customers transfer to your account number</p>
                </div>

                <div class="text-center">
                    <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">
                        4
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Get Notified</h3>
                    <p class="text-gray-600">Automatic verification and instant notifications</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-primary">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold text-white mb-4">Ready to Get Started?</h2>
            <p class="text-xl text-primary-100 mb-8">
                Join businesses using CheckoutPay to accept payments securely
            </p>
            <a href="{{ route('business.register') }}" class="bg-white text-primary px-8 py-3 rounded-lg hover:bg-gray-100 font-medium text-lg transition-colors shadow-lg inline-block">
                Create Your Account
            </a>
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
                        <h3 class="text-white font-bold text-lg">CheckoutPay</h3>
                    </div>
                    <p class="text-sm">Simple, secure payment gateway for businesses.</p>
                </div>

                <div>
                    <h4 class="text-white font-semibold mb-4">Product</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#features" class="hover:text-white">Features</a></li>
                        <li><a href="{{ route('business.register') }}" class="hover:text-white">Pricing</a></li>
                        <li><a href="#how-it-works" class="hover:text-white">How It Works</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-white font-semibold mb-4">Developers</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="https://github.com/amithyone/checkoutpay/blob/main/API_DOCUMENTATION.md" target="_blank" class="hover:text-white">API Documentation</a></li>
                        <li><a href="/api/v1/health" class="hover:text-white">API Status</a></li>
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

            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-sm">
                <p>&copy; {{ date('Y') }} CheckoutPay. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>

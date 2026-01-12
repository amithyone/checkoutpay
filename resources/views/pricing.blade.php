<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->meta_title ?? 'Pricing - CheckoutPay' }}</title>
    <meta name="description" content="{{ $page->meta_description ?? 'The cheapest payment gateway rates in Nigeria. Just 1% + ₦50 per transaction. No hidden fees, no monthly charges.' }}">
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
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center">
                        @if(\App\Models\Setting::get('site_logo'))
                            <img src="{{ asset('storage/' . \App\Models\Setting::get('site_logo')) }}" alt="Logo" class="h-10">
                        @else
                            <div class="h-10 w-10 bg-primary rounded-lg flex items-center justify-center">
                                <i class="fas fa-shield-alt text-white text-xl"></i>
                            </div>
                        @endif
                        <div class="ml-3">
                            <h1 class="text-xl font-bold text-gray-900">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</h1>
                            <p class="text-xs text-gray-500">Intelligent Payment Gateway</p>
                        </div>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="{{ route('home') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Home</a>
                    <a href="{{ route('business.login') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Login</a>
                    <a href="{{ route('business.register') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 text-sm font-medium transition-colors">Get Started</a>
                </div>
            </div>
        </div>
    </nav>

    @php
        $hero = $content['hero'] ?? [];
        $pricingCard = $content['pricing_card'] ?? [];
        $comparison = $content['comparison'] ?? [];
        $faq = $content['faq'] ?? [];
        $cta = $content['cta'] ?? [];
    @endphp

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-primary via-primary/95 to-primary/90 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            @if(isset($hero['badge_text']))
            <div class="inline-block bg-white/20 text-white px-4 py-2 rounded-full text-sm font-medium mb-6 backdrop-blur-sm">
                <i class="{{ $hero['badge_icon'] ?? 'fas fa-trophy' }} mr-2"></i> {{ $hero['badge_text'] }}
            </div>
            @endif
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-6">
                {{ $hero['title'] ?? 'Simple, Transparent Pricing' }}
            </h1>
            @if(isset($hero['description']))
            <p class="text-xl text-primary-100 mb-4 max-w-3xl mx-auto">
                {{ $hero['description'] }}
            </p>
            @endif
            <div class="inline-block bg-white/20 backdrop-blur-sm rounded-2xl px-8 py-6 mb-8">
                <div class="flex items-center justify-center space-x-2">
                    <span class="text-5xl font-bold text-white">{{ $hero['rate_percentage'] ?? '1%' }}</span>
                    <span class="text-2xl text-white/80">+</span>
                    <span class="text-5xl font-bold text-white">{{ $hero['rate_fixed'] ?? '₦50' }}</span>
                </div>
                <p class="text-white/90 mt-2">{{ $hero['rate_description'] ?? 'per successful transaction' }}</p>
            </div>
        </div>
    </section>

    <!-- Pricing Card -->
    @if(isset($pricingCard['plan_name']))
    <section class="py-20 -mt-10">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-2xl shadow-2xl border-2 border-primary overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-primary to-primary/90 p-8 md:p-12 text-center text-white">
                    @if(isset($pricingCard['badge_text']))
                    <div class="inline-block bg-white/20 backdrop-blur-sm px-4 py-2 rounded-full text-sm font-medium mb-4">
                        <i class="fas fa-check-circle mr-2"></i> {{ $pricingCard['badge_text'] }}
                    </div>
                    @endif
                    <h2 class="text-3xl md:text-4xl font-bold mb-4">{{ $pricingCard['plan_name'] }}</h2>
                    @if(isset($pricingCard['description']))
                    <p class="text-xl text-primary-100 mb-6">{{ $pricingCard['description'] }}</p>
                    @endif
                    <div class="flex items-center justify-center space-x-3 mb-4">
                        <span class="text-6xl font-bold">{{ $pricingCard['rate_percentage'] ?? '1%' }}</span>
                        <span class="text-3xl text-white/80">+</span>
                        <span class="text-6xl font-bold">{{ $pricingCard['rate_fixed'] ?? '₦50' }}</span>
                    </div>
                    <p class="text-primary-100">{{ $pricingCard['rate_description'] ?? 'per transaction' }}</p>
                </div>

                <!-- Content -->
                <div class="p-8 md:p-12">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-12">
                        <!-- What's Included -->
                        @if(isset($pricingCard['included']) && is_array($pricingCard['included']))
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-6">Everything Included</h3>
                            <div class="space-y-4">
                                @foreach($pricingCard['included'] as $item)
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-4">
                                        <i class="fas fa-check text-green-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-900">{{ is_array($item) ? ($item['title'] ?? '') : $item }}</h4>
                                        @if(is_array($item) && isset($item['description']))
                                        <p class="text-sm text-gray-600">{{ $item['description'] }}</p>
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Pricing Examples -->
                        @if(isset($pricingCard['examples']) && is_array($pricingCard['examples']))
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-6">Pricing Examples</h3>
                            <div class="bg-gray-50 rounded-xl p-6 space-y-6">
                                @foreach($pricingCard['examples'] as $example)
                                <div class="flex justify-between items-center pb-4 border-b border-gray-200">
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $example['amount'] ?? '' }}</p>
                                        @if(isset($example['calculation']))
                                        <p class="text-sm text-gray-600">{{ $example['calculation'] }}</p>
                                        @endif
                                    </div>
                                    <div class="text-right">
                                        <p class="text-2xl font-bold text-primary">{{ $example['fee'] ?? '' }}</p>
                                        <p class="text-xs text-gray-500">fee</p>
                                    </div>
                                </div>
                                @endforeach
                            </div>

                            <!-- Calculator -->
                            <div class="mt-8 bg-primary/5 rounded-xl p-6 border border-primary/20">
                                <h4 class="font-semibold text-gray-900 mb-4">Calculate Your Fees</h4>
                                <div class="space-y-3">
                                    <input type="number" id="calc-amount" placeholder="Enter amount" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                                    <div class="flex justify-between items-center pt-2">
                                        <span class="text-gray-700">Fee:</span>
                                        <span id="calc-fee" class="text-2xl font-bold text-primary">₦0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- CTA Button -->
                    <div class="text-center pt-8 border-t border-gray-200">
                        <a href="{{ route('business.register') }}" class="inline-block bg-primary text-white px-10 py-4 rounded-lg hover:bg-primary/90 font-medium text-lg transition-colors shadow-lg mb-4">
                            <i class="fas fa-rocket mr-2"></i> {{ $pricingCard['cta_text'] ?? 'Get Started Now' }}
                        </a>
                        @if(isset($pricingCard['cta_note']))
                        <p class="text-sm text-gray-500">{{ $pricingCard['cta_note'] }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
    @endif

    <!-- Comparison Section -->
    @if(isset($comparison['title']))
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">{{ $comparison['title'] }}</h2>
                @if(isset($comparison['subtitle']))
                <p class="text-lg text-gray-600">{{ $comparison['subtitle'] }}</p>
                @endif
            </div>

            @if(isset($comparison['items']) && is_array($comparison['items']))
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                @foreach($comparison['items'] as $item)
                <div class="text-center p-6 bg-gray-50 rounded-xl">
                    <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="{{ $item['icon'] ?? 'fas fa-check' }} text-primary text-2xl"></i>
                    </div>
                    <h3 class="font-bold text-gray-900 mb-2">{{ $item['title'] ?? '' }}</h3>
                    <p class="text-sm text-gray-600">{{ $item['description'] ?? '' }}</p>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </section>
    @endif

    <!-- FAQ Section -->
    @if(isset($faq['title']))
    <section class="py-20 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">{{ $faq['title'] }}</h2>
            </div>

            @if(isset($faq['items']) && is_array($faq['items']))
            <div class="space-y-6">
                @foreach($faq['items'] as $item)
                <div class="bg-white rounded-lg p-6 border border-gray-200">
                    <h3 class="font-bold text-gray-900 mb-2">{{ $item['question'] ?? '' }}</h3>
                    <p class="text-gray-600">{{ $item['answer'] ?? '' }}</p>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </section>
    @endif

    <!-- CTA Section -->
    @if(isset($cta['title']))
    <section class="py-20 bg-gradient-to-r from-primary to-primary/90">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">{{ $cta['title'] }}</h2>
            @if(isset($cta['description']))
            <p class="text-xl text-primary-100 mb-8">{{ $cta['description'] }}</p>
            @endif
            <a href="{{ route('business.register') }}" class="bg-white text-primary px-10 py-4 rounded-lg hover:bg-gray-100 font-medium text-lg transition-colors shadow-lg inline-block">
                {{ $cta['cta_text'] ?? 'Create Your Account' }}
            </a>
        </div>
    </section>
    @endif

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center mb-4">
                        @if(\App\Models\Setting::get('site_logo'))
                            <img src="{{ asset('storage/' . \App\Models\Setting::get('site_logo')) }}" alt="Logo" class="h-8 mr-2">
                        @else
                            <div class="h-8 w-8 bg-primary rounded-lg flex items-center justify-center mr-2">
                                <i class="fas fa-shield-alt text-white"></i>
                            </div>
                        @endif
                        <div>
                            <h3 class="text-white font-bold text-lg">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</h3>
                            <p class="text-xs text-gray-400">Intelligent Payment Gateway</p>
                        </div>
                    </div>
                    <p class="text-sm">The cheapest payment gateway in the market. Just 1% + ₦50 per transaction.</p>
                </div>

                <div>
                    <h4 class="text-white font-semibold mb-4">Product</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('home') }}#features" class="hover:text-white">Features</a></li>
                        <li><a href="{{ route('pricing') }}" class="hover:text-white">Pricing</a></li>
                        <li><a href="{{ route('home') }}#how-it-works" class="hover:text-white">How It Works</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-white font-semibold mb-4">Developers</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="https://github.com/amithyone/checkoutpay/blob/main/docs/API_DOCUMENTATION.md" target="_blank" class="hover:text-white">API Documentation</a></li>
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

            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-sm">
                <p>&copy; {{ date('Y') }} CheckoutPay. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Fee Calculator
        const calcInput = document.getElementById('calc-amount');
        const calcFee = document.getElementById('calc-fee');

        if (calcInput && calcFee) {
            calcInput.addEventListener('input', function() {
                const amount = parseFloat(this.value) || 0;
                if (amount > 0) {
                    const fee = Math.round((amount * 0.01) + 50);
                    calcFee.textContent = '₦' + fee.toLocaleString();
                } else {
                    calcFee.textContent = '₦0';
                }
            });
        }
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->meta_title ?? 'Pricing - CheckoutPay' }}</title>
    <meta name="description" content="{{ $page->meta_description ?? 'The cheapest payment gateway rates in Nigeria. Just 1% + ₦50 per transaction. No hidden fees, no monthly charges.' }}">
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
        <link rel="shortcut icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
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
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center flex-1">
                    <a href="{{ route('home') }}" class="flex items-center">
                        @php
                            $logo = \App\Models\Setting::get('site_logo');
                            $logoPath = $logo ? storage_path('app/public/' . $logo) : null;
                        @endphp
                        @if($logo && $logoPath && file_exists($logoPath))
                            <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="h-8 sm:h-10 object-contain" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="h-8 w-8 sm:h-10 sm:w-10 bg-primary rounded-lg flex items-center justify-center" style="display: none;">
                                <i class="fas fa-shield-alt text-white text-lg sm:text-xl"></i>
                            </div>
                        @else
                            <div class="h-8 w-8 sm:h-10 sm:w-10 bg-primary rounded-lg flex items-center justify-center">
                                <i class="fas fa-shield-alt text-white text-lg sm:text-xl"></i>
                            </div>
                        @endif
                        <div class="ml-2 sm:ml-3">
                            <h1 class="text-base sm:text-xl font-bold text-gray-900">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</h1>
                            <p class="text-xs text-gray-500 hidden sm:block">Intelligent Payment Gateway</p>
                        </div>
                    </a>
                </div>
                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-4">
                    <a href="{{ route('home') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Home</a>
                    <a href="{{ route('business.login') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Login</a>
                    <a href="{{ route('business.register') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 text-sm font-medium transition-colors">Get Started</a>
                </div>
                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" class="md:hidden p-2 rounded-md text-gray-700 hover:text-primary focus:outline-none">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
            <!-- Mobile Navigation Menu -->
            <div id="mobile-menu" class="hidden md:hidden pb-4 border-t border-gray-200 mt-2">
                <div class="flex flex-col space-y-2 pt-4">
                    <a href="{{ route('home') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Home</a>
                    <a href="{{ route('business.login') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Login</a>
                    <a href="{{ route('business.register') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 text-sm font-medium transition-colors text-center">Get Started</a>
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
    <section class="bg-gradient-to-br from-primary via-primary/95 to-primary/90 py-12 sm:py-16 md:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            @if(isset($hero['badge_text']))
            <div class="inline-block bg-white/20 text-white px-3 py-1.5 sm:px-4 sm:py-2 rounded-full text-xs sm:text-sm font-medium mb-4 sm:mb-6 backdrop-blur-sm">
                <i class="{{ $hero['badge_icon'] ?? 'fas fa-trophy' }} mr-1 sm:mr-2"></i> {{ $hero['badge_text'] }}
            </div>
            @endif
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-white mb-4 sm:mb-6 px-2">
                {{ $hero['title'] ?? 'Simple, Transparent Pricing' }}
            </h1>
            @if(isset($hero['description']))
            <p class="text-base sm:text-lg md:text-xl text-primary-100 mb-4 max-w-3xl mx-auto px-2">
                {{ $hero['description'] }}
            </p>
            @endif
            <div class="inline-block bg-white/20 backdrop-blur-sm rounded-2xl px-4 py-4 sm:px-6 sm:py-5 md:px-8 md:py-6 mb-6 sm:mb-8">
                <div class="flex items-center justify-center space-x-1 sm:space-x-2">
                    <span class="text-3xl sm:text-4xl md:text-5xl font-bold text-white">{{ $hero['rate_percentage'] ?? '1%' }}</span>
                    <span class="text-xl sm:text-2xl text-white/80">+</span>
                    <span class="text-3xl sm:text-4xl md:text-5xl font-bold text-white">{{ $hero['rate_fixed'] ?? '₦50' }}</span>
                </div>
                <p class="text-white/90 mt-2 text-sm sm:text-base">{{ $hero['rate_description'] ?? 'per successful transaction' }}</p>
            </div>
        </div>
    </section>

    <!-- Pricing Card -->
    @if(isset($pricingCard['plan_name']))
    <section class="py-12 sm:py-16 md:py-20 -mt-6 sm:-mt-8 md:-mt-10">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-2xl shadow-2xl border-2 border-primary overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-primary to-primary/90 p-6 sm:p-8 md:p-12 text-center text-white">
                    @if(isset($pricingCard['badge_text']))
                    <div class="inline-block bg-white/20 backdrop-blur-sm px-3 py-1.5 sm:px-4 sm:py-2 rounded-full text-xs sm:text-sm font-medium mb-3 sm:mb-4">
                        <i class="fas fa-check-circle mr-1 sm:mr-2"></i> {{ $pricingCard['badge_text'] }}
                    </div>
                    @endif
                    <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-3 sm:mb-4">{{ $pricingCard['plan_name'] }}</h2>
                    @if(isset($pricingCard['description']))
                    <p class="text-base sm:text-lg md:text-xl text-primary-100 mb-4 sm:mb-6 px-2">{{ $pricingCard['description'] }}</p>
                    @endif
                    <div class="flex items-center justify-center space-x-2 sm:space-x-3 mb-3 sm:mb-4">
                        <span class="text-4xl sm:text-5xl md:text-6xl font-bold">{{ $pricingCard['rate_percentage'] ?? '1%' }}</span>
                        <span class="text-2xl sm:text-3xl text-white/80">+</span>
                        <span class="text-4xl sm:text-5xl md:text-6xl font-bold">{{ $pricingCard['rate_fixed'] ?? '₦50' }}</span>
                    </div>
                    <p class="text-primary-100 text-sm sm:text-base">{{ $pricingCard['rate_description'] ?? 'per transaction' }}</p>
                </div>

                <!-- Content -->
                <div class="p-6 sm:p-8 md:p-12">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-10 md:gap-12 mb-8 sm:mb-10 md:mb-12">
                        <!-- What's Included -->
                        @if(isset($pricingCard['included']) && is_array($pricingCard['included']))
                        <div>
                            <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-4 sm:mb-6">Everything Included</h3>
                            <div class="space-y-3 sm:space-y-4">
                                @foreach($pricingCard['included'] as $item)
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 w-7 h-7 sm:w-8 sm:h-8 bg-green-100 rounded-full flex items-center justify-center mr-3 sm:mr-4">
                                        <i class="fas fa-check text-green-600 text-sm"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-sm sm:text-base text-gray-900">{{ is_array($item) ? ($item['title'] ?? '') : $item }}</h4>
                                        @if(is_array($item) && isset($item['description']))
                                        <p class="text-xs sm:text-sm text-gray-600 mt-1">{{ $item['description'] }}</p>
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
                            <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-4 sm:mb-6">Pricing Examples</h3>
                            <div class="bg-gray-50 rounded-xl p-4 sm:p-6 space-y-4 sm:space-y-6">
                                @foreach($pricingCard['examples'] as $example)
                                <div class="flex justify-between items-center pb-3 sm:pb-4 border-b border-gray-200 gap-2">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-sm sm:text-base text-gray-900 break-words">{{ $example['amount'] ?? '' }}</p>
                                        @if(isset($example['calculation']))
                                        <p class="text-xs sm:text-sm text-gray-600 mt-1">{{ $example['calculation'] }}</p>
                                        @endif
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="text-xl sm:text-2xl font-bold text-primary">{{ $example['fee'] ?? '' }}</p>
                                        <p class="text-xs text-gray-500">fee</p>
                                    </div>
                                </div>
                                @endforeach
                            </div>

                            <!-- Calculator -->
                            <div class="mt-6 sm:mt-8 bg-primary/5 rounded-xl p-4 sm:p-6 border border-primary/20">
                                <h4 class="font-semibold text-sm sm:text-base text-gray-900 mb-3 sm:mb-4">Calculate Your Fees</h4>
                                <div class="space-y-2 sm:space-y-3">
                                    <input type="number" id="calc-amount" placeholder="Enter amount" 
                                        class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                                    <div class="flex justify-between items-center pt-2">
                                        <span class="text-sm sm:text-base text-gray-700">Fee:</span>
                                        <span id="calc-fee" class="text-xl sm:text-2xl font-bold text-primary">₦0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- CTA Button -->
                    <div class="text-center pt-6 sm:pt-8 border-t border-gray-200">
                        <a href="{{ route('business.register') }}" class="w-full sm:w-auto inline-block bg-primary text-white px-6 sm:px-8 md:px-10 py-3 sm:py-4 rounded-lg hover:bg-primary/90 font-medium text-base sm:text-lg transition-colors shadow-lg mb-3 sm:mb-4">
                            <i class="fas fa-rocket mr-2"></i> {{ $pricingCard['cta_text'] ?? 'Get Started Now' }}
                        </a>
                        @if(isset($pricingCard['cta_note']))
                        <p class="text-xs sm:text-sm text-gray-500 px-2">{{ $pricingCard['cta_note'] }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
    @endif

    <!-- Comparison Section -->
    @if(isset($comparison['title']))
    <section class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-10 md:mb-12">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3 sm:mb-4">{{ $comparison['title'] }}</h2>
                @if(isset($comparison['subtitle']))
                <p class="text-base sm:text-lg text-gray-600 px-2">{{ $comparison['subtitle'] }}</p>
                @endif
            </div>

            @if(isset($comparison['items']) && is_array($comparison['items']))
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8 max-w-5xl mx-auto">
                @foreach($comparison['items'] as $item)
                <div class="text-center p-5 sm:p-6 bg-gray-50 rounded-xl">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                        <i class="{{ $item['icon'] ?? 'fas fa-check' }} text-primary text-xl sm:text-2xl"></i>
                    </div>
                    <h3 class="font-bold text-sm sm:text-base text-gray-900 mb-2">{{ $item['title'] ?? '' }}</h3>
                    <p class="text-xs sm:text-sm text-gray-600 px-2">{{ $item['description'] ?? '' }}</p>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </section>
    @endif

    <!-- FAQ Section -->
    @if(isset($faq['title']))
    <section class="py-12 sm:py-16 md:py-20 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-10 md:mb-12">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3 sm:mb-4">{{ $faq['title'] }}</h2>
            </div>

            @if(isset($faq['items']) && is_array($faq['items']))
            <div class="space-y-4 sm:space-y-6">
                @foreach($faq['items'] as $item)
                <div class="bg-white rounded-lg p-5 sm:p-6 border border-gray-200">
                    <h3 class="font-bold text-base sm:text-lg text-gray-900 mb-2">{{ $item['question'] ?? '' }}</h3>
                    <p class="text-sm sm:text-base text-gray-600">{{ $item['answer'] ?? '' }}</p>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </section>
    @endif

    <!-- CTA Section -->
    @if(isset($cta['title']))
    <section class="py-12 sm:py-16 md:py-20 bg-gradient-to-r from-primary to-primary/90">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-3 sm:mb-4 px-2">{{ $cta['title'] }}</h2>
            @if(isset($cta['description']))
            <p class="text-base sm:text-lg md:text-xl text-primary-100 mb-6 sm:mb-8 px-2">{{ $cta['description'] }}</p>
            @endif
            <a href="{{ route('business.register') }}" class="w-full sm:w-auto inline-block bg-white text-primary px-6 sm:px-8 md:px-10 py-3 sm:py-4 rounded-lg hover:bg-gray-100 font-medium text-base sm:text-lg transition-colors shadow-lg">
                {{ $cta['cta_text'] ?? 'Create Your Account' }}
            </a>
        </div>
    </section>
    @endif

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 py-8 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 sm:gap-8">
                <div>
                    <div class="flex items-center mb-3 sm:mb-4">
                        @php
                            $logo = \App\Models\Setting::get('site_logo');
                            $logoPath = $logo ? storage_path('app/public/' . $logo) : null;
                        @endphp
                        @if($logo && $logoPath && file_exists($logoPath))
                            <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="h-7 sm:h-8 mr-2 object-contain" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="h-7 w-7 sm:h-8 sm:w-8 bg-primary rounded-lg flex items-center justify-center mr-2" style="display: none;">
                                <i class="fas fa-shield-alt text-white text-sm"></i>
                            </div>
                        @else
                            <div class="h-7 w-7 sm:h-8 sm:w-8 bg-primary rounded-lg flex items-center justify-center mr-2">
                                <i class="fas fa-shield-alt text-white text-sm"></i>
                            </div>
                        @endif
                        <div>
                            <h3 class="text-white font-bold text-base sm:text-lg">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</h3>
                            <p class="text-xs text-gray-400">Intelligent Payment Gateway</p>
                        </div>
                    </div>
                    <p class="text-xs sm:text-sm">The cheapest payment gateway in the market. Just 1% + ₦50 per transaction.</p>
                </div>

                <div>
                    <h4 class="text-white font-semibold mb-3 sm:mb-4 text-sm sm:text-base">Product</h4>
                    <ul class="space-y-2 text-xs sm:text-sm">
                        <li><a href="{{ route('home') }}#features" class="hover:text-white">Features</a></li>
                        <li><a href="{{ route('pricing') }}" class="hover:text-white">Pricing</a></li>
                        <li><a href="{{ route('home') }}#how-it-works" class="hover:text-white">How It Works</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-white font-semibold mb-3 sm:mb-4 text-sm sm:text-base">Developers</h4>
                    <ul class="space-y-2 text-xs sm:text-sm">
                        <li><a href="https://github.com/amithyone/checkoutpay/blob/main/docs/API_DOCUMENTATION.md" target="_blank" class="hover:text-white break-words">API Documentation</a></li>
                        <li><a href="/api/health" class="hover:text-white">API Status</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-white font-semibold mb-3 sm:mb-4 text-sm sm:text-base">Account</h4>
                    <ul class="space-y-2 text-xs sm:text-sm">
                        <li><a href="{{ route('business.login') }}" class="hover:text-white">Login</a></li>
                        <li><a href="{{ route('business.register') }}" class="hover:text-white">Sign Up</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-800 mt-6 sm:mt-8 pt-6 sm:pt-8 text-center text-xs sm:text-sm">
                <p>&copy; {{ date('Y') }} CheckoutPay. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
                const icon = this.querySelector('i');
                if (mobileMenu.classList.contains('hidden')) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                } else {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                }
            });
        }

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

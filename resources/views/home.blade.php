<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->meta_title ?? 'CheckoutPay - Intelligent Payment Gateway' }}</title>
    @if(!empty($settings['site_favicon']))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . $settings['site_favicon']) }}">
        <link rel="shortcut icon" type="image/png" href="{{ asset('storage/' . $settings['site_favicon']) }}">
    @endif
    <meta name="description" content="{{ $page->meta_description ?? 'Payments for Nigerian businesses.' }}">
    <!-- OPTIMIZED: Preconnect to CDN domains for faster loading -->
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://cdn.tailwindcss.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <!-- Load CSS normally (must load synchronously for proper rendering) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Tailwind CSS must load before page renders (no defer) -->
    <script src="https://cdn.tailwindcss.com"></script>
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
    <!-- Navigation -->
    @include('partials.nav')

    @php
        $hero = $content['hero'] ?? [];
        $whatItIs = $content['what_it_is'] ?? [];
        $features = $content['features'] ?? [];
        $whoItsFor = $content['who_its_for'] ?? [];
        $pricingSection = $content['pricing_section'] ?? [];
        $howItWorks = $content['how_it_works'] ?? [];
        $security = $content['security'] ?? [];
        $cta = $content['cta'] ?? [];
        $footer = $content['footer'] ?? [];
    @endphp

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-primary/10 via-white to-primary/5 py-12 sm:py-16 md:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                @if(isset($hero['badge_text']) && !empty($hero['badge_text']))
                <div class="inline-block bg-green-100 text-green-800 px-3 py-1.5 sm:px-4 sm:py-2 rounded-full text-xs sm:text-sm font-medium mb-4 sm:mb-6">
                    <i class="{{ $hero['badge_icon'] ?? 'fas fa-tag' }} mr-1 sm:mr-2"></i> {{ $hero['badge_text'] }}
                </div>
                @endif
                <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-gray-900 mb-4 sm:mb-6 leading-tight">
                    {{ $hero['title'] ?? 'CheckoutPay' }}<br class="hidden sm:block">
                    @if(isset($hero['title_highlight']) && !empty($hero['title_highlight']))
                    <span class="text-primary block sm:inline">{{ $hero['title_highlight'] }}</span>
                    @endif
                </h1>
                @if(isset($hero['description']))
                <p class="text-base sm:text-lg md:text-xl text-gray-600 mb-4 max-w-3xl mx-auto px-2">
                    {{ $hero['description'] }}
                </p>
                @endif
                @if(isset($hero['pricing_text']))
                <p class="text-xl sm:text-2xl font-bold text-primary mb-6 sm:mb-8">
                    {{ $hero['pricing_text'] }}
                </p>
                @endif
                <div class="flex flex-col sm:flex-row justify-center items-center gap-3 sm:gap-4 px-4">
                    <a href="{{ route('business.register') }}" class="w-full sm:w-auto bg-primary text-white px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-primary/90 font-medium text-base sm:text-lg transition-colors shadow-lg">
                        {{ $hero['cta_primary'] ?? 'Get started' }}
                    </a>
                    <a href="{{ route('pricing') }}" class="w-full sm:w-auto bg-white text-primary border-2 border-primary px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-primary/5 font-medium text-base sm:text-lg transition-colors">
                        {{ $hero['cta_secondary'] ?? 'View pricing' }}
                    </a>
                </div>
                
                <!-- WordPress Plugin Badge -->
                <div class="mt-6 sm:mt-8 flex flex-col items-center">
                    <div class="inline-flex items-center bg-white border border-gray-200 rounded-full px-4 sm:px-5 py-2 sm:py-2.5 shadow-sm hover:shadow-md transition-shadow">
                        <i class="fab fa-wordpress text-gray-600 text-lg sm:text-xl mr-2 sm:mr-3"></i>
                        <span class="text-xs sm:text-sm text-gray-700 font-medium mr-2 sm:mr-3">WordPress Plugin</span>
                        <a href="{{ asset('downloads/checkoutpay-gateway.zip') }}" download class="text-gray-600 hover:text-gray-700 font-medium text-xs sm:text-sm flex items-center">
                            Download
                            <i class="fas fa-arrow-right ml-1.5 text-xs"></i>
                        </a>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">
                        Works with WooCommerce
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- What It Is Section -->
    @if(isset($whatItIs['description']) && !empty($whatItIs['description']))
    <section class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                @if(isset($whatItIs['title']) && !empty($whatItIs['title']))
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-4 sm:mb-6">{{ $whatItIs['title'] }}</h2>
                @endif
                <p class="text-base sm:text-lg md:text-xl text-gray-700 leading-relaxed">
                    {{ $whatItIs['description'] }}
                </p>
            </div>
        </div>
    </section>
    @endif

    <!-- Philosophy Statement 2 -->
    <section class="py-6 sm:py-8 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-sm sm:text-base text-gray-500 italic">
                Because the work should speak for itself.
            </p>
        </div>
    </section>

    <!-- Features Section -->
    @if(isset($features['items']) && is_array($features['items']) && count($features['items']) > 0)
    <section id="features" class="py-12 sm:py-16 md:py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if(isset($features['title']) && !empty($features['title']))
            <div class="text-center mb-8 sm:mb-12 md:mb-16">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3 sm:mb-4">{{ $features['title'] }}</h2>
                @if(isset($features['subtitle']) && !empty($features['subtitle']))
                <p class="text-base sm:text-lg text-gray-600 px-2">{{ $features['subtitle'] }}</p>
                @endif
            </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8">
                @foreach($features['items'] as $feature)
                <div class="bg-white p-6 sm:p-8 rounded-lg border border-gray-200 hover:shadow-lg transition-shadow">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="{{ $feature['icon'] ?? 'fas fa-check' }} text-primary text-xl sm:text-2xl"></i>
                    </div>
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-2 sm:mb-3">{{ $feature['title'] ?? '' }}</h3>
                    <p class="text-sm sm:text-base text-gray-600">{{ $feature['description'] ?? '' }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- Philosophy Statement 3 -->
    <section class="py-6 sm:py-8 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-sm sm:text-base text-gray-500 italic">
                A system that doesn't interrupt the moment.
            </p>
        </div>
    </section>

    <!-- Who It's For Section -->
    @if(isset($whoItsFor['description']) && !empty($whoItsFor['description']))
    <section class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                @if(isset($whoItsFor['title']) && !empty($whoItsFor['title']))
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-4 sm:mb-6">{{ $whoItsFor['title'] }}</h2>
                @endif
                <p class="text-base sm:text-lg md:text-xl text-gray-700 leading-relaxed">
                    {{ $whoItsFor['description'] }}
                </p>
            </div>
        </div>
    </section>
    @endif

    <!-- Philosophy Statement 4 -->
    <section class="py-6 sm:py-8 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-sm sm:text-base text-gray-500 italic">
                Nothing to manage. Nothing to announce.
            </p>
        </div>
    </section>

    <!-- Pricing Section -->
    @if(isset($pricingSection['title']))
    <section id="pricing" class="py-12 sm:py-16 md:py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-12 md:mb-16">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3 sm:mb-4">{{ $pricingSection['title'] }}</h2>
                @if(isset($pricingSection['subtitle']) && !empty($pricingSection['subtitle']))
                <p class="text-base sm:text-lg text-gray-600 px-2">{{ $pricingSection['subtitle'] }}</p>
                @endif
            </div>

            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-2xl shadow-xl border-2 border-primary p-6 sm:p-8 md:p-12">
                    <div class="text-center mb-6 sm:mb-8">
                        @if(isset($pricingSection['badge_text']) && !empty($pricingSection['badge_text']))
                        <div class="inline-block bg-green-100 text-green-800 px-3 py-1.5 sm:px-4 sm:py-2 rounded-full text-xs sm:text-sm font-medium mb-3 sm:mb-4">
                            <i class="fas fa-check-circle mr-1 sm:mr-2"></i> {{ $pricingSection['badge_text'] }}
                        </div>
                        @endif
                        <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3 sm:mb-4">{{ $pricingSection['plan_name'] ?? 'Pay As You Go' }}</h3>
                        <div class="mb-4 sm:mb-6">
                            <span class="text-3xl sm:text-4xl md:text-5xl font-bold text-primary">{{ $pricingSection['rate_percentage'] ?? '1%' }}</span>
                            <span class="text-xl sm:text-2xl text-gray-600"> + </span>
                            <span class="text-3xl sm:text-4xl md:text-5xl font-bold text-primary">{{ $pricingSection['rate_fixed'] ?? '₦50' }}</span>
                            <p class="text-sm sm:text-base text-gray-600 mt-2">{{ $pricingSection['rate_description'] ?? 'per successful transaction' }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8 mb-6 sm:mb-8">
                        @if(isset($pricingSection['included']) && is_array($pricingSection['included']))
                        <div class="space-y-3 sm:space-y-4">
                            <h4 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">What's Included:</h4>
                            <div class="space-y-2 sm:space-y-3">
                                @foreach($pricingSection['included'] as $item)
                                <div class="flex items-start">
                                    <i class="fas fa-check-circle text-green-600 mt-0.5 sm:mt-1 mr-2 sm:mr-3 flex-shrink-0"></i>
                                    <span class="text-sm sm:text-base text-gray-700 break-words">{{ is_array($item) ? ($item['title'] ?? $item) : $item }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @if(isset($pricingSection['examples']) && is_array($pricingSection['examples']))
                        <div class="bg-gray-50 rounded-lg p-4 sm:p-6">
                            <h4 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Pricing Examples:</h4>
                            <div class="space-y-3 sm:space-y-4">
                                @foreach($pricingSection['examples'] as $example)
                                <div class="flex justify-between items-center gap-2">
                                    <span class="text-sm sm:text-base text-gray-700 break-words">{{ is_array($example) ? $example['amount'] : $example }}</span>
                                    <span class="font-bold text-sm sm:text-base text-gray-900 whitespace-nowrap">{{ is_array($example) ? $example['fee'] : '' }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>

                    <div class="text-center pt-6 sm:pt-8 border-t border-gray-200">
                        <a href="{{ route('business.register') }}" class="w-full sm:w-auto inline-block bg-primary text-white px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-primary/90 font-medium text-base sm:text-lg transition-colors shadow-lg">
                            {{ $pricingSection['cta_text'] ?? 'Get started' }}
                        </a>
                        @if(isset($pricingSection['cta_note']))
                        <p class="text-xs sm:text-sm text-gray-500 mt-3 sm:mt-4 px-2">{{ $pricingSection['cta_note'] }}</p>
                        @endif
                    </div>
                </div>

                @if(isset($pricingSection['comparison_badge']) && !empty($pricingSection['comparison_badge']))
                <div class="mt-8 text-center">
                    <div class="inline-flex items-center bg-yellow-50 border border-yellow-200 rounded-lg px-6 py-3">
                        <i class="fas fa-trophy text-yellow-600 mr-3"></i>
                        <span class="text-gray-800 font-medium">{{ $pricingSection['comparison_badge'] }}</span>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </section>
    @endif

    <!-- Philosophy Statement 5 -->
    <section class="py-6 sm:py-8 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-sm sm:text-base text-gray-500 italic">
                The part you never have to think about.
            </p>
        </div>
    </section>

    <!-- WordPress Plugin Section -->
    <section class="py-12 sm:py-16 md:py-20 bg-gradient-to-br from-purple-50 via-white to-purple-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-2xl shadow-xl border border-purple-100 overflow-hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-0">
                    <!-- Left Side - Content -->
                    <div class="p-6 sm:p-8 md:p-10 lg:p-12 flex flex-col justify-center">
                        <div class="inline-flex items-center bg-purple-100 text-purple-800 px-3 py-1.5 rounded-full text-xs sm:text-sm font-medium mb-4 sm:mb-6 w-fit">
                            <i class="fab fa-wordpress mr-2"></i> WooCommerce Integration
                        </div>
                        <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-4 sm:mb-6">
                            WordPress Plugin
                        </h2>
                        <p class="text-base sm:text-lg text-gray-600 mb-6 sm:mb-8">
                            Works with WooCommerce. Install the plugin and configure your API key.
                        </p>
                        <div class="space-y-4 mb-6 sm:mb-8">
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-gray-400 mt-1 mr-3 flex-shrink-0"></i>
                                <div>
                                    <h3 class="font-semibold text-gray-900 mb-1">Installation</h3>
                                    <p class="text-sm text-gray-600">Upload and activate. Works with WordPress 5.0+ and WooCommerce 5.0+</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-gray-400 mt-1 mr-3 flex-shrink-0"></i>
                                <div>
                                    <h3 class="font-semibold text-gray-900 mb-1">Configuration</h3>
                                    <p class="text-sm text-gray-600">Enter your API key to start accepting payments.</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-gray-400 mt-1 mr-3 flex-shrink-0"></i>
                                <div>
                                    <h3 class="font-semibold text-gray-900 mb-1">Charge Management</h3>
                                    <p class="text-sm text-gray-600">Choose who pays transaction fees.</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                            <a href="{{ asset('downloads/checkoutpay-gateway.zip') }}" download class="inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium text-base sm:text-lg transition-colors shadow-lg">
                                Download
                            </a>
                            <a href="{{ route('business.register') }}" class="inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 bg-white text-purple-600 border-2 border-purple-300 rounded-lg hover:bg-purple-50 font-medium text-base sm:text-lg transition-colors">
                                Get API Key
                            </a>
                        </div>
                        <div class="mt-4 sm:mt-6 p-3 sm:p-4 bg-gray-50 rounded-lg">
                            <p class="text-xs sm:text-sm text-gray-600">
                                Version 1.0.0 | Requires WordPress 5.0+ and WooCommerce 5.0+
                            </p>
                        </div>
                    </div>
                    <!-- Right Side - Visual -->
                    <div class="bg-gradient-to-br from-purple-100 to-purple-200 p-6 sm:p-8 md:p-10 lg:p-12 flex items-center justify-center">
                        <div class="text-center">
                            <div class="inline-flex items-center justify-center w-24 h-24 sm:w-32 sm:h-32 bg-white rounded-2xl shadow-lg mb-6">
                                <i class="fab fa-wordpress text-purple-600 text-4xl sm:text-5xl"></i>
                            </div>
                            <div class="space-y-3 sm:space-y-4">
                                <div class="flex items-center justify-center space-x-2 text-gray-700">
                                    <i class="fab fa-wordpress text-xl sm:text-2xl"></i>
                                    <span class="text-sm sm:text-base font-medium">WordPress</span>
                                </div>
                                <div class="text-purple-600">
                                    <i class="fas fa-plus text-lg sm:text-xl"></i>
                                </div>
                                <div class="flex items-center justify-center space-x-2 text-gray-700">
                                    <i class="fas fa-shopping-cart text-xl sm:text-2xl"></i>
                                    <span class="text-sm sm:text-base font-medium">WooCommerce</span>
                                </div>
                                <div class="text-purple-600">
                                    <i class="fas fa-equals text-lg sm:text-xl"></i>
                                </div>
                                <div class="flex items-center justify-center space-x-2 text-purple-600">
                                    <i class="fas fa-check-circle text-xl sm:text-2xl"></i>
                                    <span class="text-sm sm:text-base font-semibold">CheckoutPay</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    @if(isset($howItWorks['title']))
    <section class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-12 md:mb-16">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3 sm:mb-4">{{ $howItWorks['title'] }}</h2>
                @if(isset($howItWorks['subtitle']) && !empty($howItWorks['subtitle']))
                <p class="text-base sm:text-lg text-gray-600 px-2">{{ $howItWorks['subtitle'] }}</p>
                @endif
            </div>

            @if(isset($howItWorks['steps']) && is_array($howItWorks['steps']))
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 sm:gap-8">
                @foreach($howItWorks['steps'] as $step)
                <div class="text-center">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 bg-primary rounded-full flex items-center justify-center text-white text-lg sm:text-xl md:text-2xl font-bold mx-auto mb-3 sm:mb-4">
                        {{ $step['number'] ?? '' }}
                    </div>
                    <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-2">{{ $step['title'] ?? '' }}</h3>
                    <p class="text-sm sm:text-base text-gray-600 px-2">{{ $step['description'] ?? '' }}</p>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </section>
    @endif

    <!-- Security Section -->
    @if(isset($security['description']) && !empty($security['description']))
    <section class="py-12 sm:py-16 md:py-20 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                @if(isset($security['title']) && !empty($security['title']))
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-4 sm:mb-6">{{ $security['title'] }}</h2>
                @endif
                <p class="text-base sm:text-lg md:text-xl text-gray-700 leading-relaxed">
                    {{ $security['description'] }}
                </p>
            </div>
        </div>
    </section>
    @endif

    <!-- CTA Section -->
    @if(isset($cta['title']))
    <section class="py-12 sm:py-16 md:py-20 bg-gradient-to-r from-primary to-primary/90">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-3 sm:mb-4 px-2">{{ $cta['title'] }}</h2>
            @if(isset($cta['description']) && !empty($cta['description']))
            <p class="text-base sm:text-lg md:text-xl text-primary-100 mb-6 sm:mb-8 px-2">{{ $cta['description'] }}</p>
            @endif
            <div class="flex flex-col sm:flex-row justify-center items-center gap-3 sm:gap-4 px-4">
                    <a href="{{ route('business.register') }}" class="w-full sm:w-auto bg-white text-primary px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-gray-100 font-medium text-base sm:text-lg transition-colors shadow-lg">
                    {{ $cta['cta_primary'] ?? 'Get started' }}
                </a>
                <a href="{{ route('pricing') }}" class="w-full sm:w-auto bg-transparent text-white border-2 border-white px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-white/10 font-medium text-base sm:text-lg transition-colors">
                    {{ $cta['cta_secondary'] ?? 'View pricing' }}
                </a>
            </div>
        </div>
    </section>
    @endif

    @include('partials.footer')

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
    </script>
</body>
</html>

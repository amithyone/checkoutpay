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
    <meta name="description" content="{{ $page->meta_description ?? 'Intelligent payment gateway for businesses. Accept payments with the cheapest rates in the market - just 1% + ₦50 per transaction.' }}">
    <!-- OPTIMIZED: Preconnect to CDN domains for faster loading -->
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://cdn.tailwindcss.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <!-- Load CSS normally (async loading was breaking styles) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    <!-- OPTIMIZED: Header is LCP element - ensure it renders quickly -->
    <header id="home" class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
    <nav class="bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center flex-1">
                    <div class="flex-shrink-0">
                        @php
                            $logo = $settings['site_logo'] ?? null;
                            $logoPath = $logo ? storage_path('app/public/' . $logo) : null;
                        @endphp
                        @if($logo && $logoPath && file_exists($logoPath))
                            <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="h-8 sm:h-10 object-contain" loading="eager" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="h-8 w-8 sm:h-10 sm:w-10 bg-primary rounded-lg flex items-center justify-center" style="display: none;">
                                <i class="fas fa-shield-alt text-white text-lg sm:text-xl"></i>
                            </div>
                        @else
                            <div class="h-8 w-8 sm:h-10 sm:w-10 bg-primary rounded-lg flex items-center justify-center">
                                <i class="fas fa-shield-alt text-white text-lg sm:text-xl"></i>
                            </div>
                        @endif
                    </div>
                    <div class="ml-2 sm:ml-3">
                        <h1 class="text-base sm:text-xl font-bold text-gray-900">{{ $settings['site_name'] ?? 'CheckoutPay' }}</h1>
                        <p class="text-xs text-gray-500 hidden sm:block">Intelligent Payment Gateway</p>
                    </div>
                </div>
                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-4">
                    <!-- Products Dropdown -->
                    <div class="relative group">
                        <a href="{{ route('products.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium flex items-center">
                            Products
                            <i class="fas fa-chevron-down ml-1 text-xs"></i>
                        </a>
                        <div class="absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <a href="{{ route('products.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Payment Gateway</a>
                            <a href="{{ route('products.index') }}#api" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">API Integration</a>
                            <a href="{{ route('products.index') }}#hosted-checkout" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Hosted Checkout</a>
                            <a href="{{ route('products.index') }}#wordpress-plugin" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">WordPress Plugin</a>
                        </div>
                    </div>
                    
                    <!-- Resources Dropdown -->
                    <div class="relative group">
                        <a href="{{ route('resources.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium flex items-center">
                            Resources
                            <i class="fas fa-chevron-down ml-1 text-xs"></i>
                        </a>
                        <div class="absolute left-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <a href="{{ route('resources.index') }}#documentation" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Documentation</a>
                            <a href="{{ route('resources.index') }}#guides" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Integration Guides</a>
                            <a href="{{ route('resources.index') }}#sdk" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">SDKs & Libraries</a>
                            <a href="{{ route('resources.index') }}#examples" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Code Examples</a>
                        </div>
                    </div>
                    
                    <a href="{{ route('pricing') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Pricing</a>
                    
                    <!-- Developers Dropdown -->
                    <div class="relative group">
                        <a href="{{ route('developers.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium flex items-center">
                            Developers
                            <i class="fas fa-chevron-down ml-1 text-xs"></i>
                        </a>
                        <div class="absolute left-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <a href="{{ route('developers.index') }}#api-reference" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">API Reference</a>
                            <a href="{{ route('developers.index') }}#webhooks" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Webhooks</a>
                            <a href="{{ route('developers.index') }}#testing" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Testing</a>
                            <a href="{{ route('business.api-documentation.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Full Documentation</a>
                        </div>
                    </div>
                    
                    <a href="{{ route('support.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Support</a>
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
                    <a href="{{ route('products.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Products</a>
                    <a href="{{ route('resources.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Resources</a>
                    <a href="{{ route('pricing') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Pricing</a>
                    <a href="{{ route('developers.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Developers</a>
                    <a href="{{ route('support.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Support</a>
                    <a href="{{ route('business.login') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Login</a>
                    <a href="{{ route('business.register') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 text-sm font-medium transition-colors text-center">Get Started</a>
                </div>
            </div>
        </div>
    </nav>
    </header>

    @php
        $hero = $content['hero'] ?? [];
        $features = $content['features'] ?? [];
        $pricingSection = $content['pricing_section'] ?? [];
        $howItWorks = $content['how_it_works'] ?? [];
        $cta = $content['cta'] ?? [];
        $footer = $content['footer'] ?? [];
    @endphp

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-primary/10 via-white to-primary/5 py-12 sm:py-16 md:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                @if(isset($hero['badge_text']))
                <div class="inline-block bg-green-100 text-green-800 px-3 py-1.5 sm:px-4 sm:py-2 rounded-full text-xs sm:text-sm font-medium mb-4 sm:mb-6">
                    <i class="{{ $hero['badge_icon'] ?? 'fas fa-tag' }} mr-1 sm:mr-2"></i> {{ $hero['badge_text'] }}
                </div>
                @endif
                <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-gray-900 mb-4 sm:mb-6 leading-tight">
                    {{ $hero['title'] ?? 'Intelligent Payment Gateway' }}<br class="hidden sm:block">
                    @if(isset($hero['title_highlight']))
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
                        <i class="fas fa-rocket mr-2"></i> {{ $hero['cta_primary'] ?? 'Get Started Free' }}
                    </a>
                    <a href="{{ route('pricing') }}" class="w-full sm:w-auto bg-white text-primary border-2 border-primary px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-primary/5 font-medium text-base sm:text-lg transition-colors">
                        {{ $hero['cta_secondary'] ?? 'View Pricing' }}
                    </a>
                </div>
                
                <!-- WordPress Plugin Badge -->
                <div class="mt-6 sm:mt-8 flex flex-col items-center">
                    <div class="inline-flex items-center bg-white border border-purple-200 rounded-full px-4 sm:px-5 py-2 sm:py-2.5 shadow-sm hover:shadow-md transition-shadow">
                        <i class="fab fa-wordpress text-purple-600 text-lg sm:text-xl mr-2 sm:mr-3"></i>
                        <span class="text-xs sm:text-sm text-gray-700 font-medium mr-2 sm:mr-3">WordPress Plugin Available</span>
                        <a href="{{ asset('downloads/checkoutpay-gateway.zip') }}" download class="text-purple-600 hover:text-purple-700 font-semibold text-xs sm:text-sm flex items-center">
                            Download
                            <i class="fas fa-arrow-right ml-1.5 text-xs"></i>
                        </a>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">
                        <i class="fas fa-check-circle text-green-500 mr-1"></i>
                        Works with WooCommerce
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    @if(isset($features['title']))
    <section id="features" class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-12 md:mb-16">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3 sm:mb-4">{{ $features['title'] }}</h2>
                @if(isset($features['subtitle']))
                <p class="text-base sm:text-lg text-gray-600 px-2">{{ $features['subtitle'] }}</p>
                @endif
            </div>

            @if(isset($features['items']) && is_array($features['items']))
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
            @endif
        </div>
    </section>
    @endif

    <!-- Pricing Section -->
    @if(isset($pricingSection['title']))
    <section id="pricing" class="py-12 sm:py-16 md:py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-12 md:mb-16">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3 sm:mb-4">{{ $pricingSection['title'] }}</h2>
                @if(isset($pricingSection['subtitle']))
                <p class="text-base sm:text-lg text-gray-600 px-2">{{ $pricingSection['subtitle'] }}</p>
                @endif
            </div>

            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-2xl shadow-xl border-2 border-primary p-6 sm:p-8 md:p-12">
                    <div class="text-center mb-6 sm:mb-8">
                        @if(isset($pricingSection['badge_text']))
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
                            {{ $pricingSection['cta_text'] ?? 'Get Started Now' }}
                        </a>
                        @if(isset($pricingSection['cta_note']))
                        <p class="text-xs sm:text-sm text-gray-500 mt-3 sm:mt-4 px-2">{{ $pricingSection['cta_note'] }}</p>
                        @endif
                    </div>
                </div>

                @if(isset($pricingSection['comparison_badge']))
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
                            WordPress Plugin Available
                        </h2>
                        <p class="text-base sm:text-lg text-gray-600 mb-6 sm:mb-8">
                            Quick integration for WooCommerce stores. Install our plugin and start accepting payments in minutes. No coding required!
                        </p>
                        <div class="space-y-4 mb-6 sm:mb-8">
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                <div>
                                    <h3 class="font-semibold text-gray-900 mb-1">Easy Installation</h3>
                                    <p class="text-sm text-gray-600">Upload and activate in seconds. Works with WordPress 5.0+ and WooCommerce 5.0+</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                <div>
                                    <h3 class="font-semibold text-gray-900 mb-1">Automatic Configuration</h3>
                                    <p class="text-sm text-gray-600">Enter your API key and you're ready to accept payments. No complex setup needed.</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                <div>
                                    <h3 class="font-semibold text-gray-900 mb-1">Charge Management</h3>
                                    <p class="text-sm text-gray-600">Choose whether you or your customers pay transaction fees. Fully customizable.</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                            <a href="{{ asset('downloads/checkoutpay-gateway.zip') }}" download class="inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium text-base sm:text-lg transition-colors shadow-lg">
                                <i class="fas fa-download mr-2"></i> Download Plugin
                            </a>
                            <a href="{{ route('business.register') }}" class="inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 bg-white text-purple-600 border-2 border-purple-300 rounded-lg hover:bg-purple-50 font-medium text-base sm:text-lg transition-colors">
                                <i class="fas fa-key mr-2"></i> Get API Key
                            </a>
                        </div>
                        <div class="mt-4 sm:mt-6 p-3 sm:p-4 bg-gray-50 rounded-lg">
                            <p class="text-xs sm:text-sm text-gray-600">
                                <i class="fas fa-info-circle mr-2 text-purple-600"></i>
                                <strong>Version 1.0.0</strong> | Requires WordPress 5.0+ and WooCommerce 5.0+ | Free to use
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
                @if(isset($howItWorks['subtitle']))
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

    <!-- CTA Section -->
    @if(isset($cta['title']))
    <section class="py-12 sm:py-16 md:py-20 bg-gradient-to-r from-primary to-primary/90">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-3 sm:mb-4 px-2">{{ $cta['title'] }}</h2>
            @if(isset($cta['description']))
            <p class="text-base sm:text-lg md:text-xl text-primary-100 mb-6 sm:mb-8 px-2">{{ $cta['description'] }}</p>
            @endif
            <div class="flex flex-col sm:flex-row justify-center items-center gap-3 sm:gap-4 px-4">
                <a href="{{ route('business.register') }}" class="w-full sm:w-auto bg-white text-primary px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-gray-100 font-medium text-base sm:text-lg transition-colors shadow-lg">
                    {{ $cta['cta_primary'] ?? 'Create Your Account' }}
                </a>
                <a href="{{ route('pricing') }}" class="w-full sm:w-auto bg-transparent text-white border-2 border-white px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-white/10 font-medium text-base sm:text-lg transition-colors">
                    {{ $cta['cta_secondary'] ?? 'View Pricing' }}
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

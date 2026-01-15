<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->meta_title ?? 'CheckoutPay - Intelligent Payment Gateway' }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
        <link rel="shortcut icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <meta name="description" content="{{ $page->meta_description ?? 'Intelligent payment gateway for businesses. Accept payments with the cheapest rates in the market - just 1% + ₦50 per transaction.' }}">
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
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        @php
                            $logo = \App\Models\Setting::get('site_logo');
                            $logoPath = $logo ? storage_path('app/public/' . $logo) : null;
                        @endphp
                        @if($logo && $logoPath && file_exists($logoPath))
                            <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="h-10 object-contain" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="h-10 w-10 bg-primary rounded-lg flex items-center justify-center" style="display: none;">
                                <i class="fas fa-shield-alt text-white text-xl"></i>
                            </div>
                        @else
                            <div class="h-10 w-10 bg-primary rounded-lg flex items-center justify-center">
                                <i class="fas fa-shield-alt text-white text-xl"></i>
                            </div>
                        @endif
                    </div>
                    <div class="ml-3">
                        <h1 class="text-xl font-bold text-gray-900">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</h1>
                        <p class="text-xs text-gray-500">Intelligent Payment Gateway</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="{{ route('pricing') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Pricing</a>
                    <a href="{{ route('business.login') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Login</a>
                    <a href="{{ route('business.register') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 text-sm font-medium transition-colors">Get Started</a>
                </div>
            </div>
        </div>
    </nav>

    @php
        $hero = $content['hero'] ?? [];
        $features = $content['features'] ?? [];
        $pricingSection = $content['pricing_section'] ?? [];
        $howItWorks = $content['how_it_works'] ?? [];
        $cta = $content['cta'] ?? [];
        $footer = $content['footer'] ?? [];
    @endphp

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-primary/10 via-white to-primary/5 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                @if(isset($hero['badge_text']))
                <div class="inline-block bg-green-100 text-green-800 px-4 py-2 rounded-full text-sm font-medium mb-6">
                    <i class="{{ $hero['badge_icon'] ?? 'fas fa-tag' }} mr-2"></i> {{ $hero['badge_text'] }}
                </div>
                @endif
                <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-6">
                    {{ $hero['title'] ?? 'Intelligent Payment Gateway' }}<br>
                    @if(isset($hero['title_highlight']))
                    <span class="text-primary">{{ $hero['title_highlight'] }}</span>
                    @endif
                </h1>
                @if(isset($hero['description']))
                <p class="text-xl text-gray-600 mb-4 max-w-3xl mx-auto">
                    {{ $hero['description'] }}
                </p>
                @endif
                @if(isset($hero['pricing_text']))
                <p class="text-2xl font-bold text-primary mb-8">
                    {{ $hero['pricing_text'] }}
                </p>
                @endif
                <div class="flex justify-center space-x-4">
                    <a href="{{ route('business.register') }}" class="bg-primary text-white px-8 py-4 rounded-lg hover:bg-primary/90 font-medium text-lg transition-colors shadow-lg">
                        <i class="fas fa-rocket mr-2"></i> {{ $hero['cta_primary'] ?? 'Get Started Free' }}
                    </a>
                    <a href="{{ route('pricing') }}" class="bg-white text-primary border-2 border-primary px-8 py-4 rounded-lg hover:bg-primary/5 font-medium text-lg transition-colors">
                        {{ $hero['cta_secondary'] ?? 'View Pricing' }}
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    @if(isset($features['title']))
    <section id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">{{ $features['title'] }}</h2>
                @if(isset($features['subtitle']))
                <p class="text-lg text-gray-600">{{ $features['subtitle'] }}</p>
                @endif
            </div>

            @if(isset($features['items']) && is_array($features['items']))
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach($features['items'] as $feature)
                <div class="bg-white p-8 rounded-lg border border-gray-200 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="{{ $feature['icon'] ?? 'fas fa-check' }} text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">{{ $feature['title'] ?? '' }}</h3>
                    <p class="text-gray-600">{{ $feature['description'] ?? '' }}</p>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </section>
    @endif

    <!-- Pricing Section -->
    @if(isset($pricingSection['title']))
    <section id="pricing" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">{{ $pricingSection['title'] }}</h2>
                @if(isset($pricingSection['subtitle']))
                <p class="text-lg text-gray-600">{{ $pricingSection['subtitle'] }}</p>
                @endif
            </div>

            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-2xl shadow-xl border-2 border-primary p-8 md:p-12">
                    <div class="text-center mb-8">
                        @if(isset($pricingSection['badge_text']))
                        <div class="inline-block bg-green-100 text-green-800 px-4 py-2 rounded-full text-sm font-medium mb-4">
                            <i class="fas fa-check-circle mr-2"></i> {{ $pricingSection['badge_text'] }}
                        </div>
                        @endif
                        <h3 class="text-3xl font-bold text-gray-900 mb-4">{{ $pricingSection['plan_name'] ?? 'Pay As You Go' }}</h3>
                        <div class="mb-6">
                            <span class="text-5xl font-bold text-primary">{{ $pricingSection['rate_percentage'] ?? '1%' }}</span>
                            <span class="text-2xl text-gray-600"> + </span>
                            <span class="text-5xl font-bold text-primary">{{ $pricingSection['rate_fixed'] ?? '₦50' }}</span>
                            <p class="text-gray-600 mt-2">{{ $pricingSection['rate_description'] ?? 'per successful transaction' }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        @if(isset($pricingSection['included']) && is_array($pricingSection['included']))
                        <div class="space-y-4">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">What's Included:</h4>
                            <div class="space-y-3">
                                @foreach($pricingSection['included'] as $item)
                                <div class="flex items-start">
                                    <i class="fas fa-check-circle text-green-600 mt-1 mr-3"></i>
                                    <span class="text-gray-700">{{ is_array($item) ? ($item['title'] ?? $item) : $item }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @if(isset($pricingSection['examples']) && is_array($pricingSection['examples']))
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">Pricing Examples:</h4>
                            <div class="space-y-4">
                                @foreach($pricingSection['examples'] as $example)
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-700">{{ is_array($example) ? $example['amount'] : $example }}</span>
                                    <span class="font-bold text-gray-900">{{ is_array($example) ? $example['fee'] : '' }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>

                    <div class="text-center pt-8 border-t border-gray-200">
                        <a href="{{ route('business.register') }}" class="bg-primary text-white px-8 py-4 rounded-lg hover:bg-primary/90 font-medium text-lg transition-colors shadow-lg inline-block">
                            {{ $pricingSection['cta_text'] ?? 'Get Started Now' }}
                        </a>
                        @if(isset($pricingSection['cta_note']))
                        <p class="text-sm text-gray-500 mt-4">{{ $pricingSection['cta_note'] }}</p>
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

    <!-- How It Works -->
    @if(isset($howItWorks['title']))
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">{{ $howItWorks['title'] }}</h2>
                @if(isset($howItWorks['subtitle']))
                <p class="text-lg text-gray-600">{{ $howItWorks['subtitle'] }}</p>
                @endif
            </div>

            @if(isset($howItWorks['steps']) && is_array($howItWorks['steps']))
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                @foreach($howItWorks['steps'] as $step)
                <div class="text-center">
                    <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">
                        {{ $step['number'] ?? '' }}
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ $step['title'] ?? '' }}</h3>
                    <p class="text-gray-600">{{ $step['description'] ?? '' }}</p>
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
            <div class="flex justify-center space-x-4">
                <a href="{{ route('business.register') }}" class="bg-white text-primary px-8 py-4 rounded-lg hover:bg-gray-100 font-medium text-lg transition-colors shadow-lg">
                    {{ $cta['cta_primary'] ?? 'Create Your Account' }}
                </a>
                <a href="{{ route('pricing') }}" class="bg-transparent text-white border-2 border-white px-8 py-4 rounded-lg hover:bg-white/10 font-medium text-lg transition-colors">
                    {{ $cta['cta_secondary'] ?? 'View Pricing' }}
                </a>
            </div>
        </div>
    </section>
    @endif

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center mb-4">
                        @php
                            $logo = \App\Models\Setting::get('site_logo');
                            $logoPath = $logo ? storage_path('app/public/' . $logo) : null;
                        @endphp
                        @if($logo && $logoPath && file_exists($logoPath))
                            <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="h-8 mr-2 object-contain" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="h-8 w-8 bg-primary rounded-lg flex items-center justify-center mr-2" style="display: none;">
                                <i class="fas fa-shield-alt text-white"></i>
                            </div>
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
                    @if(isset($footer['description']))
                    <p class="text-sm">{{ $footer['description'] }}</p>
                    @endif
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

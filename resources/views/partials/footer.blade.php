@php
    $logo = \App\Models\Setting::get('site_logo');
    $logoPath = $logo ? storage_path('app/public/' . $logo) : null;
@endphp
<footer class="bg-gray-900 text-gray-300 py-8 sm:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 sm:gap-8">
            <div>
                <div class="flex items-center mb-3 sm:mb-4">
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
                    <h3 class="text-white font-bold text-base sm:text-lg">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</h3>
                </div>
                <p class="text-xs sm:text-sm text-gray-400">Intelligent Payment Gateway</p>
            </div>
            <div>
                <h4 class="text-white font-semibold mb-3 sm:mb-4 text-sm sm:text-base">Product</h4>
                <ul class="space-y-2 text-xs sm:text-sm">
                    <li><a href="{{ route('products.index') }}" class="hover:text-white">Products</a></li>
                    <li><a href="{{ route('pricing') }}" class="hover:text-white">Pricing</a></li>
                    <li><a href="{{ route('home') }}#features" class="hover:text-white">Features</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white font-semibold mb-3 sm:mb-4 text-sm sm:text-base">Developers</h4>
                <ul class="space-y-2 text-xs sm:text-sm">
                    <li><a href="{{ route('developers.index') }}" class="hover:text-white">API Reference</a></li>
                    <li><a href="{{ route('resources.index') }}" class="hover:text-white">Documentation</a></li>
                    <li><a href="{{ asset('downloads/checkoutpay-gateway.zip') }}" download class="hover:text-white"><i class="fab fa-wordpress mr-1"></i> WordPress Plugin</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white font-semibold mb-3 sm:mb-4 text-sm sm:text-base">Support</h4>
                <ul class="space-y-2 text-xs sm:text-sm">
                    <li><a href="{{ route('support.index') }}" class="hover:text-white">Help Center</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-white">Contact Us</a></li>
                    <li><a href="{{ route('business.login') }}" class="hover:text-white">Login</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-gray-800 mt-6 sm:mt-8 pt-6 sm:pt-8">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex flex-wrap justify-center md:justify-start gap-4 sm:gap-6 text-xs sm:text-sm">
                    <a href="{{ route('privacy-policy') }}" class="text-gray-400 hover:text-white transition-colors">Privacy Policy</a>
                    <a href="{{ route('terms') }}" class="text-gray-400 hover:text-white transition-colors">Terms & Conditions</a>
                    <a href="{{ route('contact') }}" class="text-gray-400 hover:text-white transition-colors">Contact Us</a>
                </div>
                <p class="text-xs sm:text-sm text-gray-400 mt-4 md:mt-0">&copy; {{ date('Y') }} {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</footer>

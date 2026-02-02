@php
    $logo = \App\Models\Setting::get('site_logo');
    $logoPath = $logo ? storage_path('app/public/' . $logo) : null;
@endphp
<footer class="bg-gray-900 text-gray-300 py-8 sm:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-5 gap-6 sm:gap-8">
            <!-- Products Column -->
            <div>
                <h4 class="text-white font-semibold mb-3 sm:mb-4 text-sm sm:text-base">Products</h4>
                <ul class="space-y-2 text-xs sm:text-sm">
                    <li><a href="{{ route('products.index') }}" class="hover:text-white transition-colors">Products</a></li>
                    <li><a href="{{ route('products.invoices') }}" class="hover:text-white transition-colors">Invoices</a></li>
                    <li><a href="{{ route('rentals.index') }}" class="hover:text-white transition-colors">Rentals</a></li>
                    <li><a href="{{ route('products.rentals-info') }}" class="hover:text-white transition-colors text-gray-400">What are Rentals?</a></li>
                    <li><a href="{{ route('memberships.index') }}" class="hover:text-white transition-colors">Memberships</a></li>
                    <li><a href="{{ route('products.memberships-info') }}" class="hover:text-white transition-colors text-gray-400">What are Memberships?</a></li>
                    <li><a href="{{ route('tickets.index') }}" class="hover:text-white transition-colors">Tickets</a></li>
                    <li><a href="{{ route('products.tickets-info') }}" class="hover:text-white transition-colors text-gray-400">What are Tickets?</a></li>
                    <li><a href="{{ route('payout.index') }}" class="hover:text-white transition-colors">Payout</a></li>
                    <li><a href="{{ route('collections.index') }}" class="hover:text-white transition-colors">Collections</a></li>
                    <li><a href="{{ route('checkout-demo.index') }}" class="hover:text-white transition-colors">Checkout Demo</a></li>
                </ul>
            </div>

            <!-- Company Column -->
            <div>
                <h4 class="text-white font-semibold mb-3 sm:mb-4 text-sm sm:text-base">Company</h4>
                <ul class="space-y-2 text-xs sm:text-sm">
                    <li><a href="{{ route('about.index') }}" class="hover:text-white transition-colors">About Us</a></li>
                    <li><a href="{{ route('pricing') }}" class="hover:text-white transition-colors">Pricing</a></li>
                </ul>
            </div>

            <!-- Learn & Resources Column -->
            <div>
                <h4 class="text-white font-semibold mb-3 sm:mb-4 text-sm sm:text-base">Learn & Resources</h4>
                <ul class="space-y-2 text-xs sm:text-sm">
                    <li><a href="{{ route('api-docs') }}" class="hover:text-white transition-colors font-semibold">API Documentation</a></li>
                    <li><a href="{{ route('support.index') }}" class="hover:text-white transition-colors">Support</a></li>
                    <li><a href="{{ route('blog.index') }}" class="hover:text-white transition-colors">Blog</a></li>
                    <li><a href="{{ route('developers.index') }}" class="hover:text-white transition-colors">Developers</a></li>
                    <li><a href="{{ route('status.index') }}" class="hover:text-white transition-colors">Status</a></li>
                    <li><a href="{{ route('faqs.index') }}" class="hover:text-white transition-colors">FAQs</a></li>
                </ul>
            </div>

            <!-- Legal & Security Column -->
            <div>
                <h4 class="text-white font-semibold mb-3 sm:mb-4 text-sm sm:text-base">Legal & Security</h4>
                <ul class="space-y-2 text-xs sm:text-sm">
                    <li><a href="{{ route('security') }}" class="hover:text-white transition-colors">Security</a></li>
                    <li><a href="{{ route('terms') }}" class="hover:text-white transition-colors">Terms and Conditions</a></li>
                    <li><a href="{{ route('privacy-policy') }}" class="hover:text-white transition-colors">Privacy Policy</a></li>
                    <li><a href="{{ route('esg-policy') }}" class="hover:text-white transition-colors">ESG Policy</a></li>
                    <li><a href="{{ route('fraud-awareness') }}" class="hover:text-white transition-colors">Fraud Awareness</a></li>
                </ul>
            </div>

            <!-- Logo & Brand Column -->
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
                <p class="text-xs sm:text-sm text-gray-400 mb-4">Intelligent Payment Gateway</p>
                <div class="flex space-x-4">
                    <a href="{{ route('business.login') }}" class="text-gray-400 hover:text-white transition-colors text-sm">Login</a>
                    <a href="{{ route('business.register') }}" class="text-gray-400 hover:text-white transition-colors text-sm">Sign Up</a>
                </div>
            </div>
        </div>
        <div class="border-t border-gray-800 mt-6 sm:mt-8 pt-6 sm:pt-8">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <p class="text-xs sm:text-sm text-gray-400">&copy; {{ date('Y') }} {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}. All rights reserved.</p>
                <div class="flex flex-wrap justify-center md:justify-end gap-4 sm:gap-6 mt-4 md:mt-0 text-xs sm:text-sm">
                    <a href="{{ route('contact') }}" class="text-gray-400 hover:text-white transition-colors">Contact Us</a>
                </div>
            </div>
        </div>
    </div>
</footer>

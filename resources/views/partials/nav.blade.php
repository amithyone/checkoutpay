@php
    $logo = \App\Models\Setting::get('site_logo');
    $logoPath = $logo ? storage_path('app/public/' . $logo) : null;
@endphp
<nav class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center flex-1">
                <div class="flex-shrink-0">
                    @if($logo && $logoPath && file_exists($logoPath))
                        <a href="{{ route('home') }}">
                            <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="h-8 sm:h-10 object-contain" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="h-8 w-8 sm:h-10 sm:w-10 bg-primary rounded-lg flex items-center justify-center" style="display: none;">
                                <i class="fas fa-shield-alt text-white text-lg sm:text-xl"></i>
                            </div>
                        </a>
                    @else
                        <a href="{{ route('home') }}">
                            <div class="h-8 w-8 sm:h-10 sm:w-10 bg-primary rounded-lg flex items-center justify-center">
                                <i class="fas fa-shield-alt text-white text-lg sm:text-xl"></i>
                            </div>
                        </a>
                    @endif
                </div>
                <div class="ml-2 sm:ml-3">
                    <a href="{{ route('home') }}">
                        <h1 class="text-base sm:text-xl font-bold text-gray-900">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</h1>
                        <p class="text-xs text-gray-500 hidden sm:block">Intelligent Payment Gateway</p>
                    </a>
                </div>
            </div>
            <div class="hidden md:flex items-center space-x-3">
                <div class="relative group">
                    <a href="{{ route('products.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium flex items-center {{ request()->routeIs('products.*') || request()->routeIs('rentals.*') || request()->routeIs('payout.*') || request()->routeIs('collections.*') || request()->routeIs('checkout-demo.*') || request()->routeIs('charity.*') ? 'text-primary border-b-2 border-primary' : '' }}">
                        Products <i class="fas fa-chevron-down ml-1 text-xs"></i>
                    </a>
                    <div class="absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                        <a href="{{ route('products.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Products</a>
                        <a href="{{ route('products.invoices') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Invoices</a>
                        <a href="{{ route('rentals.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Rentals</a>
                        <a href="{{ route('memberships.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Memberships</a>
                        <a href="{{ route('tickets.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Tickets</a>
                        <a href="{{ route('payout.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Payout</a>
                        <a href="{{ route('collections.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Collections</a>
                        <a href="{{ route('charity.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">GoFund & Charity</a>
                        <a href="{{ route('checkout-demo.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Checkout Demo</a>
                    </div>
                </div>
                <a href="{{ route('pricing') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('pricing') || request()->routeIs('about.*') ? 'text-primary border-b-2 border-primary' : '' }}">Pricing</a>
                <div class="relative group">
                    <a href="{{ route('developers.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium flex items-center {{ request()->routeIs('developers.*') ? 'text-primary border-b-2 border-primary' : '' }}">
                        Developers <i class="fas fa-chevron-down ml-1 text-xs"></i>
                    </a>
                    <div class="absolute left-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                        <a href="{{ route('developers.index') }}#api-reference" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">API Reference</a>
                        <a href="{{ route('developers.index') }}#webhooks" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Webhooks</a>
                        <a href="{{ route('developers.index') }}#testing" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Testing</a>
                        <a href="{{ route('business.api-documentation.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Full Documentation</a>
                    </div>
                </div>
                <a href="{{ route('support.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('support.*') ? 'text-primary border-b-2 border-primary' : '' }}">Support</a>
                <div class="relative group">
                    <a href="{{ route('resources.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium flex items-center {{ request()->routeIs('resources.*') || request()->routeIs('blog.*') || request()->routeIs('status.*') || request()->routeIs('faqs.*') || request()->routeIs('api-docs') ? 'text-primary border-b-2 border-primary' : '' }}">
                        Resources <i class="fas fa-chevron-down ml-1 text-xs"></i>
                    </a>
                    <div class="absolute left-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                        <a href="{{ route('api-docs') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary font-semibold">
                            <i class="fas fa-code mr-2 text-primary"></i>API Documentation
                        </a>
                        <div class="border-t border-gray-200 my-1"></div>
                        <a href="{{ route('resources.index') }}#documentation" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Documentation</a>
                        <a href="{{ route('resources.index') }}#guides" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Integration Guides</a>
                        <a href="{{ route('resources.index') }}#sdk" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">SDKs & Libraries</a>
                        <a href="{{ route('resources.index') }}#examples" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Code Examples</a>
                        <div class="border-t border-gray-200 my-1"></div>
                        <a href="{{ route('blog.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Blog</a>
                        <a href="{{ route('status.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Status</a>
                        <a href="{{ route('faqs.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">FAQs</a>
                    </div>
                </div>
                @auth('web')
                    <a href="{{ route('user.dashboard') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">My Account</a>
                @elseauth('renter')
                    <a href="{{ route('renter.dashboard') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Rentals Dashboard</a>
                @elseauth('business')
                    <a href="{{ route('business.dashboard') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                @else
                    <a href="{{ route('account.login') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">My Account</a>
                    <a href="{{ route('business.login') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Business</a>
                    <a href="{{ route('business.register') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 text-sm font-medium transition-colors">Get Started</a>
                @endauth
            </div>
            <button id="mobile-menu-btn" class="md:hidden p-2 rounded-md text-gray-700 hover:text-primary focus:outline-none">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </div>
        <div id="mobile-menu" class="hidden md:hidden pb-4 border-t border-gray-200 mt-2">
                <div class="flex flex-col space-y-2 pt-4">
                <a href="{{ route('products.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Products</a>
                <a href="{{ route('products.invoices') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium ml-4">Invoices</a>
                <a href="{{ route('rentals.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium ml-4">Rentals</a>
                <a href="{{ route('memberships.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium ml-4">Memberships</a>
                <a href="{{ route('tickets.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium ml-4">Tickets</a>
                <a href="{{ route('payout.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium ml-4">Payout</a>
                <a href="{{ route('collections.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium ml-4">Collections</a>
                <a href="{{ route('charity.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium ml-4">GoFund & Charity</a>
                <a href="{{ route('checkout-demo.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium ml-4">Checkout Demo</a>
                <a href="{{ route('pricing') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Pricing</a>
                <a href="{{ route('developers.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Developers</a>
                <a href="{{ route('support.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Support</a>
                <a href="{{ route('resources.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Resources</a>
                <a href="{{ route('api-docs') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium ml-4 font-semibold">
                    <i class="fas fa-code mr-2 text-primary"></i>API Documentation
                </a>
                <a href="{{ route('blog.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium ml-4">Blog</a>
                <a href="{{ route('status.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium ml-4">Status</a>
                <a href="{{ route('faqs.index') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium ml-4">FAQs</a>
                @auth('web')
                    <a href="{{ route('user.dashboard') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">My Account</a>
                @elseauth('renter')
                    <a href="{{ route('renter.dashboard') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Rentals Dashboard</a>
                @elseauth('business')
                    <a href="{{ route('business.dashboard') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                @else
                    <a href="{{ route('account.login') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">My Account</a>
                    <a href="{{ route('business.login') }}" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Business</a>
                    <a href="{{ route('business.register') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 text-sm font-medium transition-colors text-center">Get Started</a>
                @endauth
            </div>
        </div>
    </div>
</nav>
<script>
    document.getElementById('mobile-menu-btn')?.addEventListener('click', () => {
        document.getElementById('mobile-menu')?.classList.toggle('hidden');
    });
</script>

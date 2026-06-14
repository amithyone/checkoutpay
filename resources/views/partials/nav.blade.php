@php
    $logo = \App\Models\Setting::get('site_logo');
    $logoPath = $logo ? storage_path('app/public/' . $logo) : null;
@endphp
<nav class="fixed top-0 w-full z-50 bg-white/85 backdrop-blur-xl border-b border-slate-100 shadow-sm">
    <div class="max-w-container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
            <div class="flex items-center flex-1 gap-6 lg:gap-10">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 group shrink-0">
                    @if($logo && $logoPath && file_exists($logoPath))
                        <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="h-9 sm:h-10 object-contain group-hover:scale-105 transition-transform" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="h-10 w-10 bg-brand-primary rounded-xl flex items-center justify-center shadow-lg shadow-brand-primary/25 group-hover:scale-105 transition-transform" style="display: none;">
                            <i class="fas fa-shield-alt text-white text-lg"></i>
                        </div>
                    @else
                        <div class="h-10 w-10 bg-brand-primary rounded-xl flex items-center justify-center shadow-lg shadow-brand-primary/25 group-hover:scale-105 transition-transform">
                            <i class="fas fa-shield-alt text-white text-lg"></i>
                        </div>
                    @endif
                    <div class="flex flex-col">
                        <span class="font-sans text-lg sm:text-2xl font-black tracking-tight text-midnight-deep leading-none">
                            {{ $siteName ?? \App\Support\SiteBranding::name() }}
                        </span>
                        <span class="text-[10px] font-semibold text-slate-400 tracking-wider uppercase hidden sm:block">Intelligent Payment Gateway</span>
                    </div>
                </a>
            </div>
            <div class="hidden md:flex items-center space-x-1 lg:space-x-2">
                <div class="relative group">
                    <a href="{{ route('products.index') }}" class="nav-link-marketing flex items-center px-3 {{ request()->routeIs('products.*') || request()->routeIs('whatsapp-wallet.*') || request()->routeIs('rentals.*') || request()->routeIs('payout.*') || request()->routeIs('collections.*') || request()->routeIs('checkout-demo.*') || request()->routeIs('charity.*') ? '!text-brand-primary' : '' }}">
                        Products <i class="fas fa-chevron-down ml-1 text-[10px] opacity-60"></i>
                    </a>
                    <div class="absolute left-0 mt-2 w-52 bg-white rounded-xl shadow-brand border border-slate-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                        <a href="{{ route('whatsapp-wallet.index') }}" class="block px-4 py-2 text-sm text-green-700 hover:bg-green-50 font-semibold border-b border-gray-100">
                            <i class="fab fa-whatsapp mr-2"></i>WhatsApp Wallet
                        </a>
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
                <a href="{{ route('pricing') }}" class="nav-link-marketing px-3 {{ request()->routeIs('pricing') || request()->routeIs('about.*') ? '!text-brand-primary' : '' }}">Pricing</a>
                <div class="relative group">
                    <a href="{{ route('developers.index') }}" class="nav-link-marketing flex items-center px-3 {{ request()->routeIs('developers.*') ? '!text-brand-primary' : '' }}">
                        Developers <i class="fas fa-chevron-down ml-1 text-[10px] opacity-60"></i>
                    </a>
                    <div class="absolute left-0 mt-2 w-56 bg-white rounded-xl shadow-brand border border-slate-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                        <a href="{{ route('developers.program') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary font-semibold">Developer Program</a>
                        <a href="{{ url('/developers/program/apply') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Apply to program</a>
                        <div class="border-t border-gray-200 my-1"></div>
                        <a href="{{ route('developers.index') }}#api-reference" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">API Reference</a>
                        <a href="{{ route('developers.index') }}#webhooks" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Webhooks</a>
                        <a href="{{ route('developers.index') }}#testing" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Testing</a>
                        <a href="{{ route('api-docs') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Full Documentation</a>
                        @auth('business')
                            <a href="{{ route('business.api-documentation.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Dashboard API docs</a>
                        @endauth
                    </div>
                </div>
                <a href="{{ route('support.index') }}" class="nav-link-marketing px-3 {{ request()->routeIs('support.*') ? '!text-brand-primary' : '' }}">Support</a>
                <div class="relative group">
                    <a href="{{ route('resources.index') }}" class="nav-link-marketing flex items-center px-3 {{ request()->routeIs('resources.*') || request()->routeIs('blog.*') || request()->routeIs('status.*') || request()->routeIs('faqs.*') || request()->routeIs('api-docs') ? '!text-brand-primary' : '' }}">
                        Resources <i class="fas fa-chevron-down ml-1 text-[10px] opacity-60"></i>
                    </a>
                    <div class="absolute left-0 mt-2 w-56 bg-white rounded-xl shadow-brand border border-slate-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
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
                    <a href="{{ route('user.dashboard') }}" class="nav-link-marketing px-3">My Account</a>
                @elseauth('renter')
                    <a href="{{ route('renter.dashboard') }}" class="nav-link-marketing px-3">Rentals Dashboard</a>
                @elseauth('business')
                    <a href="{{ route('business.dashboard') }}" class="nav-link-marketing px-3">Dashboard</a>
                @else
                    <a href="{{ route('business.login') }}" class="text-sm font-semibold text-slate-600 hover:text-brand-primary transition-colors px-4 py-2 hover:bg-slate-50 rounded-xl">Login</a>
                    <a href="{{ route('business.register') }}" class="inline-flex items-center gap-2 bg-brand-primary text-white px-5 py-2.5 rounded-xl hover:bg-brand-secondary text-sm font-semibold shadow-lg shadow-brand-primary/20 transition-all">
                        Get Started <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                @endauth
            </div>
            <button type="button" id="mobile-menu-btn" class="md:hidden p-2.5 rounded-xl text-slate-600 hover:text-brand-primary hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-primary/30" aria-expanded="false" aria-controls="mobile-menu" aria-label="Open menu">
                <i class="fas fa-bars text-xl" id="mobile-menu-icon"></i>
            </button>
        </div>
        <div id="mobile-menu" class="hidden md:hidden border-t border-slate-100 relative z-[60] bg-white/95 backdrop-blur-xl pb-4" role="menu" aria-label="Mobile navigation">
            <div class="pt-3 pb-4 px-2">
                <div class="grid grid-cols-2 gap-x-2 gap-y-1 text-sm">
                    <a href="{{ route('whatsapp-wallet.index') }}" class="text-green-700 hover:text-green-800 hover:bg-green-50 px-2.5 py-2 rounded-lg font-semibold">WhatsApp Wallet</a>
                    <a href="{{ route('products.index') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Products</a>
                    <a href="{{ route('pricing') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Pricing</a>
                    <a href="{{ route('products.invoices') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Invoices</a>
                    <a href="{{ route('developers.index') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Developers</a>
                    <a href="{{ route('developers.program') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Dev program</a>
                    <a href="{{ url('/developers/program/apply') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium touch-manipulation">Apply to program</a>
                    <a href="{{ route('rentals.index') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Rentals</a>
                    <a href="{{ route('support.index') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Support</a>
                    <a href="{{ route('memberships.index') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Memberships</a>
                    <a href="{{ route('resources.index') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Resources</a>
                    <a href="{{ route('tickets.index') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Tickets</a>
                    <a href="{{ route('api-docs') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium"><i class="fas fa-code mr-1 text-primary text-xs"></i>API Docs</a>
                    <a href="{{ route('payout.index') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Payout</a>
                    <a href="{{ route('blog.index') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Blog</a>
                    <a href="{{ route('collections.index') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Collections</a>
                    <a href="{{ route('status.index') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Status</a>
                    <a href="{{ route('charity.index') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Charity</a>
                    <a href="{{ route('faqs.index') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">FAQs</a>
                    <a href="{{ route('checkout-demo.index') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Checkout Demo</a>
                    <a href="{{ route('careers') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Careers</a>
                    <a href="{{ route('contact') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Contact Us</a>
                    @auth('web')
                        <a href="{{ route('user.dashboard') }}" class="text-primary font-semibold hover:bg-primary/10 px-2.5 py-2 rounded-lg col-span-2">My Account</a>
                    @elseauth('renter')
                        <a href="{{ route('renter.dashboard') }}" class="text-primary font-semibold hover:bg-primary/10 px-2.5 py-2 rounded-lg col-span-2">Rentals Dashboard</a>
                    @elseauth('business')
                        <a href="{{ route('business.dashboard') }}" class="text-primary font-semibold hover:bg-primary/10 px-2.5 py-2 rounded-lg col-span-2">Dashboard</a>
                    @else
                        <a href="{{ route('account.login') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">My Account</a>
                        <a href="{{ route('business.login') }}" class="text-gray-700 hover:text-primary hover:bg-gray-50 px-2.5 py-2 rounded-lg font-medium">Business</a>
                        <a href="{{ route('business.register') }}" class="bg-primary text-white hover:bg-primary/90 px-2.5 py-2 rounded-lg font-semibold text-center col-span-2">Get Started</a>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</nav>
<div class="h-20 shrink-0" aria-hidden="true"></div>
<script>
(function() {
    function initMobileMenu() {
        var btn = document.getElementById('mobile-menu-btn');
        var menu = document.getElementById('mobile-menu');
        var icon = document.getElementById('mobile-menu-icon');
        if (!btn || !menu) return;
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var isHidden = menu.classList.contains('hidden');
            menu.classList.toggle('hidden');
            if (icon) {
                icon.classList.toggle('fa-bars', isHidden);
                icon.classList.toggle('fa-times', !isHidden);
            }
            btn.setAttribute('aria-expanded', !isHidden);
            btn.setAttribute('aria-label', isHidden ? 'Close menu' : 'Open menu');
        });
        menu.addEventListener('click', function(e) {
            var el = e.target;
            while (el && el !== menu) {
                if (el.tagName === 'A' && el.getAttribute('href')) {
                    menu.classList.add('hidden');
                    if (icon) {
                        icon.classList.add('fa-bars');
                        icon.classList.remove('fa-times');
                    }
                    btn.setAttribute('aria-expanded', 'false');
                    btn.setAttribute('aria-label', 'Open menu');
                    break;
                }
                el = el.parentElement;
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileMenu);
    } else {
        initMobileMenu();
    }
})();
</script>

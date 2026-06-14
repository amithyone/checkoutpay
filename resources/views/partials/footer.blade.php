@php
    $logo = \App\Models\Setting::get('site_logo');
    $logoPath = $logo ? storage_path('app/public/' . $logo) : null;
    $siteName = \App\Models\Setting::get('site_name', 'CheckoutPay');
@endphp
<footer class="bg-slate-50 border-t border-slate-200">
    <div class="max-w-container mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-8">
            <div class="col-span-2 lg:col-span-2 space-y-4">
                <div class="flex items-center gap-2">
                    @if($logo && $logoPath && file_exists($logoPath))
                        <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="h-8 object-contain" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="h-8 w-8 bg-brand-primary rounded-lg flex items-center justify-center" style="display: none;">
                            <i class="fas fa-shield-alt text-white text-sm"></i>
                        </div>
                    @else
                        <div class="h-8 w-8 bg-brand-primary rounded-lg flex items-center justify-center">
                            <i class="fas fa-shield-alt text-white text-sm"></i>
                        </div>
                    @endif
                    <span class="font-sans text-xl font-black tracking-tight text-brand-primary leading-none">{{ $siteName }}</span>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed font-semibold max-w-xs">
                    Intelligent Payment Gateway. Built for Nigerian businesses. Powered by <span class="text-slate-700">METRAVON INNOVATION LTD</span>.
                </p>
                <div class="flex gap-4 pt-1">
                    <a href="{{ route('business.login') }}" class="text-xs font-semibold text-slate-500 hover:text-brand-primary transition-colors">Login</a>
                    <a href="{{ route('business.register') }}" class="text-xs font-semibold text-slate-500 hover:text-brand-primary transition-colors">Sign Up</a>
                </div>
            </div>

            <div class="space-y-4">
                <h5 class="text-[10px] font-extrabold text-midnight-deep uppercase tracking-widest">Products</h5>
                <ul class="space-y-2.5 text-xs font-semibold">
                    <li><a href="{{ route('whatsapp-wallet.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">WhatsApp Wallet</a></li>
                    <li><a href="{{ route('products.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Products</a></li>
                    <li><a href="{{ route('products.invoices') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Invoices</a></li>
                    <li><a href="{{ route('rentals.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Rentals</a></li>
                    <li><a href="{{ route('memberships.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Memberships</a></li>
                    <li><a href="{{ route('tickets.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Tickets</a></li>
                    <li><a href="{{ route('payout.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Payout</a></li>
                    <li><a href="{{ route('collections.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Collections</a></li>
                    <li><a href="{{ route('charity.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">GoFund & Charity</a></li>
                    <li><a href="{{ route('checkout-demo.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Checkout Demo</a></li>
                </ul>
            </div>

            <div class="space-y-4">
                <h5 class="text-[10px] font-extrabold text-midnight-deep uppercase tracking-widest">Company</h5>
                <ul class="space-y-2.5 text-xs font-semibold">
                    <li><a href="{{ route('about.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">About Us</a></li>
                    <li><a href="{{ route('pricing') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Pricing</a></li>
                    <li><a href="{{ route('careers') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Careers</a></li>
                    <li><a href="{{ route('contact') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Contact Us</a></li>
                </ul>
            </div>

            <div class="space-y-4">
                <h5 class="text-[10px] font-extrabold text-midnight-deep uppercase tracking-widest">Integrate</h5>
                <ul class="space-y-2.5 text-xs font-semibold">
                    <li><a href="{{ route('api-docs') }}" class="text-slate-500 hover:text-brand-primary transition-colors">API Documentation</a></li>
                    <li><a href="{{ route('wordpress-plugin.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">WordPress / WooCommerce</a></li>
                    <li><a href="{{ route('developers.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Developers</a></li>
                    <li><a href="{{ route('developers.program') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Developer Program</a></li>
                </ul>
            </div>

            <div class="space-y-4">
                <h5 class="text-[10px] font-extrabold text-midnight-deep uppercase tracking-widest">Learn & Legal</h5>
                <ul class="space-y-2.5 text-xs font-semibold">
                    <li><a href="{{ route('faqs.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">FAQs</a></li>
                    <li><a href="{{ route('support.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Support</a></li>
                    <li><a href="{{ route('blog.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Blog</a></li>
                    <li><a href="{{ route('resources.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Resources</a></li>
                    <li><a href="{{ route('status.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Status</a></li>
                    <li><a href="{{ route('security') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Security</a></li>
                    <li><a href="{{ route('terms') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Terms</a></li>
                    <li><a href="{{ route('privacy-policy') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Privacy</a></li>
                    <li><a href="{{ route('account-deletion') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Account deletion</a></li>
                    <li><a href="{{ route('esg-policy') }}" class="text-slate-500 hover:text-brand-primary transition-colors">ESG Policy</a></li>
                    <li><a href="{{ route('fraud-awareness') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Fraud Awareness</a></li>
                    <li><a href="{{ route('site-map.index') }}" class="text-slate-500 hover:text-brand-primary transition-colors">Site map</a></li>
                </ul>
            </div>
        </div>

        <div class="border-t border-slate-200 mt-10 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-xs font-semibold text-slate-400">&copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
            <a href="{{ route('contact') }}" class="text-xs font-semibold text-slate-500 hover:text-brand-primary transition-colors">Contact Us</a>
        </div>
    </div>
</footer>
@if(!request()->routeIs('admin.*') && !request()->routeIs('business.*'))
    @include('partials.support-widget')
@endif

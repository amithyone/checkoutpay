@php
    use App\Support\MarketingAssets;

    $imageProducts = [
        [
            'route' => 'checkout-demo.index',
            'image' => MarketingAssets::url('online-payments'),
            'icon' => 'fa-credit-card',
            'title' => 'Online Payments',
            'desc' => 'Receive NGN payments through bank transfers with reliable matching and virtual accounts.',
            'tags' => ['Instant Reconciliation', 'Automated Webhooks'],
        ],
        [
            'route' => 'products.invoices',
            'image' => MarketingAssets::url('invoices'),
            'icon' => 'fa-file-invoice',
            'title' => 'Smart Invoices',
            'desc' => 'Generate and send professional invoices to customers in seconds. Track every payment in real-time.',
            'tags' => [],
        ],
        [
            'route' => 'tickets.index',
            'image' => MarketingAssets::url('tickets'),
            'icon' => 'fa-ticket-alt',
            'title' => 'Event Tickets',
            'desc' => 'Create and manage event tickets with built-in QR scanning and validation features.',
            'tags' => [],
        ],
    ];
@endphp

<section id="commerce" class="py-20 bg-white">
    <div class="px-4 sm:px-6 lg:px-8 max-w-container mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-16 space-y-4">
            <h2 class="section-heading">Unified Commerce Infrastructure</h2>
            <p class="section-subheading mx-auto">One platform to manage your entire financial ecosystem. From checkout to specialized collections.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
            @foreach($imageProducts as $product)
                <a href="{{ route($product['route']) }}" class="group block">
                    <div class="h-64 rounded-2xl overflow-hidden mb-6 relative shadow-premium">
                        <img src="{{ $product['image'] }}" alt="{{ $product['title'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                        <div class="absolute top-4 left-4 glass-marketing p-2.5 rounded-lg">
                            <i class="fas {{ $product['icon'] }} text-brand-primary"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-midnight-deep mb-2">{{ $product['title'] }}</h3>
                    <p class="text-slate-500 text-sm font-medium mb-4">{{ $product['desc'] }}</p>
                    @if(count($product['tags']) > 0)
                        <div class="flex flex-wrap gap-3 text-xs font-bold text-brand-primary">
                            @foreach($product['tags'] as $tag)
                                <span class="flex items-center gap-1"><i class="fas fa-bolt text-[10px]"></i> {{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif
                </a>
            @endforeach

            <a href="{{ route('memberships.index') }}" class="bg-surface-container-low p-8 rounded-2xl group hover:shadow-premium transition-all block">
                <i class="fas fa-users text-brand-primary text-3xl mb-6"></i>
                <h3 class="text-xl font-bold text-midnight-deep mb-2">Memberships</h3>
                <p class="text-slate-500 text-sm font-medium">Automate recurring membership subscriptions with easy renewal management and customer portals.</p>
            </a>

            <a href="{{ route('rentals.index') }}" class="group relative overflow-hidden rounded-2xl min-h-[300px] block shadow-premium">
                <img src="{{ MarketingAssets::url('rentals') }}" alt="Rentals" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                <div class="absolute inset-0 bg-gradient-to-t from-midnight-deep/85 via-midnight-deep/25 to-transparent p-8 flex flex-col justify-end">
                    <h3 class="text-xl font-bold text-white mb-2">Rentals</h3>
                    <p class="text-white/80 text-sm font-medium">Handle rental payments and deposits with security and ease.</p>
                </div>
            </a>

            <a href="{{ route('payout.index') }}" class="bg-midnight-deep p-8 rounded-2xl group hover:opacity-95 transition-all text-white block shadow-premium">
                <i class="fas fa-wallet text-white text-3xl mb-6"></i>
                <h3 class="text-xl font-bold mb-2">Payouts &amp; Collections</h3>
                <p class="text-white/70 text-sm font-medium">Global disbursements and unified collections for enterprises and high-growth startups.</p>
                <div class="mt-8 pt-6 border-t border-white/10 flex items-center gap-2 text-xs font-bold text-success-green">
                    <span class="w-2 h-2 rounded-full bg-success-green animate-pulse"></span> Secure settlement
                </div>
            </a>
        </div>

        <div class="text-center mt-12">
            <a href="{{ route('products.index') }}" class="btn-brand-outline">View all products</a>
        </div>
    </div>
</section>

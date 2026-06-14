@php
    use App\Support\CheckoutPayWordPressPlugin;
@endphp

<section id="woocommerce" class="py-20 bg-surface-container-low/40">
    <div class="px-4 sm:px-6 lg:px-8 max-w-container mx-auto">
        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            <div class="relative order-2 lg:order-1">
                <div class="bg-midnight-deep rounded-2xl p-8 shadow-2xl relative z-10">
                    <div class="flex items-center justify-between mb-8">
                        <div class="flex gap-1.5">
                            <div class="w-3 h-3 rounded-full bg-[#FF5F56]"></div>
                            <div class="w-3 h-3 rounded-full bg-[#FFBD2E]"></div>
                            <div class="w-3 h-3 rounded-full bg-[#27C93F]"></div>
                        </div>
                        <span class="text-xs text-white/50 font-mono">v{{ CheckoutPayWordPressPlugin::version() }}</span>
                    </div>
                    <div class="bg-white/5 rounded-xl p-6 border border-white/10 mb-2">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-10 h-10 bg-brand-primary/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-terminal text-brand-electric"></i>
                            </div>
                            <div>
                                <p class="font-bold text-sm text-white">CheckoutPay for WooCommerce</p>
                                <p class="text-xs text-white/40">Official Payment Gateway Plugin</p>
                            </div>
                        </div>
                        <div class="font-mono text-sm text-white/80 space-y-2">
                            <p class="text-success-green">// Installation</p>
                            <p>1. Upload &amp; Activate</p>
                            <p>2. Enter API Key</p>
                            <p>3. Start Accepting Payments</p>
                        </div>
                    </div>
                </div>
                <div class="absolute -top-6 -right-6 w-32 h-32 bg-brand-primary/10 blur-3xl -z-0"></div>
            </div>

            <div class="space-y-6 order-1 lg:order-2">
                <span class="text-xs font-bold uppercase tracking-widest text-brand-primary">WordPress Plugin</span>
                <h2 class="section-heading">Seamless Integration with WooCommerce</h2>
                <p class="section-subheading">Install the plugin, configure your API key, and you're live. Works with WordPress 5.8+ and WooCommerce 7.0+. Accept bank transfers and WhatsApp Pay Code at checkout.</p>
                <div class="space-y-5">
                    <div class="flex items-start gap-4">
                        <div class="p-2.5 bg-brand-primary/5 rounded-lg shrink-0">
                            <i class="fas fa-download text-brand-primary"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-midnight-deep text-sm">One-Click Install</h4>
                            <p class="text-sm text-slate-500 font-medium">Download and activate directly from your dashboard.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="p-2.5 bg-brand-primary/5 rounded-lg shrink-0">
                            <i class="fas fa-cog text-brand-primary"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-midnight-deep text-sm">Charge Management</h4>
                            <p class="text-sm text-slate-500 font-medium">Choose who pays transaction fees — you or your customer.</p>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-4 pt-2">
                    <x-checkoutpay-plugin-download class="inline-flex items-center gap-2 bg-midnight-deep text-white font-bold text-sm px-8 py-4 rounded-xl hover:opacity-90 transition-all shadow-premium">
                        Download Plugin <i class="fas fa-download text-xs"></i>
                    </x-checkoutpay-plugin-download>
                    <a href="{{ route('wordpress-plugin.index') }}" class="btn-brand-outline">Plugin details</a>
                </div>
            </div>
        </div>
    </div>
</section>

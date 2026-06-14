<section id="products" class="py-20 bg-surface-container-low/50">
    <div class="px-4 sm:px-6 lg:px-8 max-w-container mx-auto">
        <div class="bg-primary-container rounded-[2rem] overflow-hidden relative group">
            <div class="grid lg:grid-cols-2">
                <div class="p-10 md:p-16 space-y-6 z-10">
                    <span class="text-xs font-bold uppercase tracking-widest text-white/70">Flagship Feature</span>
                    <h2 class="section-heading text-white">WhatsApp Wallet</h2>
                    <p class="text-lg text-white/90 font-medium max-w-md leading-relaxed">
                        Send money to any Nigerian bank account or WhatsApp contact, buy airtime, pay bills, and settle checkout orders with a Pay Code — all from the chat app your customers already use every day.
                    </p>
                    <ul class="space-y-2 text-sm font-semibold text-white/85 max-w-md">
                        <li class="flex items-start gap-2"><i class="fas fa-check-circle text-success-green mt-0.5 shrink-0"></i> Bank transfers, wallet-to-wallet, and partner pay</li>
                        <li class="flex items-start gap-2"><i class="fas fa-check-circle text-success-green mt-0.5 shrink-0"></i> Checkout Pay Code at merchant checkout — customer sends <code class="bg-white/10 px-1 rounded text-xs">PAY CODE</code></li>
                        <li class="flex items-start gap-2"><i class="fas fa-check-circle text-success-green mt-0.5 shrink-0"></i> Same wallet powers the CheckoutNow app on Google Play &amp; App Store</li>
                    </ul>
                    <div class="flex flex-wrap gap-3 pt-2">
                        <a href="{{ route('whatsapp-wallet.index') }}" class="inline-flex items-center gap-2 bg-white text-brand-primary font-bold text-sm px-8 py-4 rounded-xl hover:bg-white/95 transition-all shadow-premium">
                            Learn more <i class="fas fa-arrow-right text-xs"></i>
                        </a>
                        <a href="{{ route('api-docs') }}#whatsapp-pay-code" class="inline-flex items-center gap-2 border border-white/30 text-white font-bold text-sm px-6 py-4 rounded-xl hover:bg-white/10 transition-all">
                            API docs
                        </a>
                    </div>
                </div>
                <div class="relative min-h-[320px] lg:min-h-[400px] flex items-center justify-center p-8">
                    <div class="glass-marketing p-6 rounded-2xl w-full max-w-md shadow-2xl relative z-10 -rotate-2 group-hover:rotate-0 transition-transform duration-500">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-full bg-success-green/20 flex items-center justify-center">
                                <i class="fab fa-whatsapp text-success-green text-lg"></i>
                            </div>
                            <div>
                                <p class="font-bold text-sm text-midnight-deep">WhatsApp Wallet</p>
                                <p class="text-[10px] text-slate-500">Active now</p>
                            </div>
                        </div>
                        <div class="space-y-3 text-sm">
                            <div class="bg-surface-container-high p-3 rounded-lg rounded-tl-none max-w-[85%] text-midnight-deep">
                                Send NGN 5,000 to John Doe?
                            </div>
                            <div class="bg-brand-primary text-white p-3 rounded-lg rounded-tr-none ml-auto max-w-[85%]">
                                Yes, confirm payment.
                            </div>
                            <div class="bg-success-green/10 border border-success-green/20 p-3 rounded-lg text-success-green flex items-center gap-2 font-semibold">
                                <i class="fas fa-check-circle"></i> Transaction Successful!
                            </div>
                        </div>
                    </div>
                    <div class="absolute inset-0 overflow-hidden pointer-events-none">
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[150%] h-[150%] bg-[radial-gradient(circle,rgba(255,255,255,0.15)_0%,transparent_70%)]"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

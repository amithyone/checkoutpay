@extends('layouts.marketing')

@section('title')
    @include('partials.marketing-head', [
        'seoPath' => '/whatsapp-wallet',
        'jsonLdExtra' => [\App\Support\FaqCatalog::faqPageJsonLd(\App\Support\FaqCatalog::forCategory('whatsapp-wallet'))],
    ])
@endsection

@section('content')
    @php
        use App\Support\WhatsappWalletMarketing as Wa;
        $waContact = Wa::contactUrl();
        $waBrand = Wa::brandName();
    @endphp

    {{-- Hero --}}
    <section class="relative overflow-hidden py-14 sm:py-20 md:py-24">
        <div class="max-w-container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center">
                <div>
                    <div class="badge-brand mb-5 bg-emerald-50 border-emerald-200 text-emerald-800">
                        <i class="fab fa-whatsapp"></i> Flagship service
                    </div>
                    <h1 class="section-heading leading-tight mb-5">
                        Send money to <span class="text-emerald-600">anyone</span> — right from WhatsApp
                    </h1>
                    <p class="text-lg text-slate-600 font-medium mb-4 leading-relaxed">
                        Your WhatsApp number is your wallet. Pay a friend, send to any Nigerian bank, buy airtime, or move value across borders — without leaving the app where you already chat every day.
                    </p>
                    <p class="text-base text-gray-500 mb-8">
                        Built by CheckoutPay. Same wallet powers <strong>{{ $waBrand }}</strong> and merchant checkouts across Nigeria.
                    </p>
                    <div class="flex flex-col sm:flex-row flex-wrap gap-3">
                        @if($waContact)
                            <a href="{{ $waContact }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 px-6 py-3.5 bg-wa text-white rounded-lg hover:bg-wa-dark font-semibold shadow-lg">
                                <i class="fab fa-whatsapp text-xl"></i> Open on WhatsApp
                            </a>
                        @endif
                        <a href="{{ Wa::appUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center px-6 py-3.5 bg-primary text-white rounded-lg hover:bg-primary/90 font-semibold shadow-lg">
                            Open wallet app
                        </a>
                        <a href="{{ route('business.register') }}" class="inline-flex items-center justify-center px-6 py-3.5 bg-white border-2 border-gray-200 text-gray-800 rounded-lg hover:border-primary hover:text-primary font-medium">
                            Business &amp; API
                        </a>
                    </div>
                    @unless($waContact)
                        <p class="mt-4 text-sm text-gray-500">Message <strong>WALLET</strong> on our official WhatsApp line to get started, or open the wallet web app above.</p>
                    @endunless
                </div>
                <div class="relative">
                    <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 p-6 sm:p-8 max-w-md mx-auto lg:ml-auto">
                        <div class="flex items-center gap-3 border-b border-gray-100 pb-4 mb-4">
                            <div class="w-10 h-10 rounded-full bg-wa/20 flex items-center justify-center">
                                <i class="fab fa-whatsapp text-wa-dark text-xl"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">CheckoutPay Wallet</p>
                                <p class="text-xs text-gray-500">Today, 2:14 PM</p>
                            </div>
                        </div>
                        <div class="space-y-3 text-sm">
                            <div class="bg-green-50 rounded-2xl rounded-tl-sm p-4 text-gray-800">
                                <p class="font-medium mb-1">You sent ₦15,000</p>
                                <p class="text-gray-600">To Ada — 080••• •••45 · GTBank</p>
                                <p class="text-xs text-green-700 mt-2"><i class="fas fa-check-circle"></i> Delivered</p>
                            </div>
                            <div class="bg-gray-100 rounded-2xl rounded-tr-sm p-4 text-gray-800 ml-8">
                                <p>Thanks! Got it 🙏</p>
                            </div>
                            <div class="bg-green-50 rounded-2xl rounded-tl-sm p-4 text-gray-800">
                                <p class="font-medium mb-1">P2P received ₦5,000</p>
                                <p class="text-gray-600">From Chidi (WhatsApp wallet)</p>
                            </div>
                        </div>
                        <p class="text-center text-xs text-gray-400 mt-6">Illustration — real flows use secure PIN confirmation</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Pitch --}}
    <section class="py-12 bg-white border-y border-gray-100">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-4">Pay the person you are already talking to</h2>
            <p class="text-lg text-gray-600 leading-relaxed">
                Families, freelancers, and small businesses coordinate in WhatsApp. The wallet removes the awkward step of copying long account numbers into chat. Choose a contact or type an amount — send to <strong>their bank</strong> or <strong>their WhatsApp wallet</strong> — and confirm with your PIN.
            </p>
        </div>
    </section>

    {{-- How it works --}}
    <section class="py-14 sm:py-16 bg-gray-50">
        <div class="max-w-container mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 text-center mb-4">How it works</h2>
            <p class="text-center text-gray-600 mb-12 max-w-2xl mx-auto">Three steps. No new social network — the same number you use for messages.</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white rounded-xl border border-gray-200 p-6 text-center">
                    <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-wa/15 flex items-center justify-center text-wa-dark font-bold text-lg">1</div>
                    <h3 class="font-bold text-gray-900 mb-2">Open your wallet</h3>
                    <p class="text-sm text-gray-600">Send <strong>WALLET</strong> on WhatsApp or open the {{ $waBrand }} web app. Your phone number becomes your wallet ID after a quick setup and PIN.</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-6 text-center">
                    <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-wa/15 flex items-center justify-center text-wa-dark font-bold text-lg">2</div>
                    <h3 class="font-bold text-gray-900 mb-2">Choose who gets paid</h3>
                    <p class="text-sm text-gray-600">Transfer to a saved bank payee, any Nigerian account, another WhatsApp number (P2P), or pay a business that sent you a secure payment link.</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-6 text-center">
                    <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-wa/15 flex items-center justify-center text-wa-dark font-bold text-lg">3</div>
                    <h3 class="font-bold text-gray-900 mb-2">Confirm &amp; done</h3>
                    <p class="text-sm text-gray-600">Enter your wallet PIN (and email OTP on higher tiers when enabled). You and the recipient get clear receipts in WhatsApp.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- What you can do --}}
    <section class="py-14 sm:py-16 bg-white">
        <div class="max-w-container mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 text-center mb-12">What you can do with WhatsApp Wallet</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach([
                    ['icon' => 'fa-paper-plane', 'title' => 'Send to any bank', 'text' => 'Pay any Nigerian bank account from WhatsApp — saved payees or new account + bank name.'],
                    ['icon' => 'fa-user-friends', 'title' => 'WhatsApp-to-WhatsApp (P2P)', 'text' => 'Send to another wallet by phone number. They open WALLET to receive — like cash across the table, in chat.'],
                    ['icon' => 'fa-globe-africa', 'title' => 'Cross-border sends', 'text' => 'When currencies differ, see what you pay and what they receive before you confirm — transparent conversion for family and diaspora.'],
                    ['icon' => 'fa-mobile-alt', 'title' => 'Airtime & data', 'text' => 'Top up your line or someone else\'s from the wallet menu — Nigerian networks supported.'],
                    ['icon' => 'fa-receipt', 'title' => 'Pay businesses', 'text' => 'Merchants can request payment via WhatsApp: you get an order summary and a secure PIN link — no card required.'],
                    ['icon' => 'fa-university', 'title' => 'Fund & withdraw', 'text' => 'Add money via bank transfer to your virtual account (Tier 2) or receive P2P. Withdraw to your own bank when you are ready.'],
                    ['icon' => 'fa-headset', 'title' => 'Support refunds', 'text' => 'CheckoutPay support can credit approved refunds to your WhatsApp wallet; transfer out to any bank you choose.'],
                    ['icon' => 'fa-shield-alt', 'title' => 'PIN-protected', 'text' => 'Transfers and merchant debits require your wallet PIN on a secure Checkout page — never PIN-less pulls from your balance.'],
                    ['icon' => 'fa-store', 'title' => 'Checkout services', 'text' => 'Access rentals, invoices, and more from the WhatsApp menu when linked to CheckoutPay businesses.'],
                ] as $item)
                <div class="p-6 rounded-xl border border-gray-200 hover:border-wa/40 hover:shadow-md transition">
                    <i class="fas {{ $item['icon'] }} text-wa-dark text-2xl mb-4"></i>
                    <h3 class="font-bold text-gray-900 mb-2">{{ $item['title'] }}</h3>
                    <p class="text-sm text-gray-600">{{ $item['text'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Tiers --}}
    <section class="py-14 sm:py-16 bg-gradient-to-br from-gray-50 to-green-50/30">
        <div class="max-w-container mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 text-center mb-4">Wallet tiers</h2>
            <p class="text-center text-gray-600 mb-10 max-w-2xl mx-auto">Start fast on WhatsApp. Upgrade when you need a permanent bank account number and higher limits.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <div class="bg-white rounded-2xl border-2 border-wa/30 p-8 shadow-sm">
                    <span class="text-xs font-semibold uppercase tracking-wide text-wa-dark">Tier 1</span>
                    <h3 class="text-xl font-bold text-gray-900 mt-2 mb-3">WhatsApp identity</h3>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex gap-2"><i class="fas fa-check text-green-500 mt-0.5"></i> Your WhatsApp number = wallet ID</li>
                        <li class="flex gap-2"><i class="fas fa-check text-green-500 mt-0.5"></i> P2P, bank send, airtime, merchant pay links</li>
                        <li class="flex gap-2"><i class="fas fa-check text-green-500 mt-0.5"></i> Balance cap ₦{{ Wa::tier1MaxBalance() }}</li>
                        <li class="flex gap-2"><i class="fas fa-check text-green-500 mt-0.5"></i> Daily send limit ₦{{ Wa::tier1DailyLimit() }}</li>
                    </ul>
                </div>
                <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
                    <span class="text-xs font-semibold uppercase tracking-wide text-primary">Tier 2</span>
                    <h3 class="text-xl font-bold text-gray-900 mt-2 mb-3">Verified + virtual account</h3>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex gap-2"><i class="fas fa-check text-green-500 mt-0.5"></i> Complete KYC in wallet menu (<strong>UPGRADE</strong>)</li>
                        <li class="flex gap-2"><i class="fas fa-check text-green-500 mt-0.5"></i> Permanent Nigerian virtual account for pay-ins</li>
                        <li class="flex gap-2"><i class="fas fa-check text-green-500 mt-0.5"></i> Higher limits for everyday business use</li>
                        <li class="flex gap-2"><i class="fas fa-check text-green-500 mt-0.5"></i> Full transaction history on web app</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- For businesses --}}
    <section class="py-14 sm:py-16 bg-white">
        <div class="max-w-container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-4">For businesses &amp; developers</h2>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        Accept payment inside WhatsApp without building your own bot infrastructure. Use the same CheckoutPay API key as bank-transfer checkout.
                    </p>
                    <ul class="space-y-4 text-sm text-gray-700">
                        <li class="flex gap-3">
                            <i class="fas fa-code text-primary mt-1"></i>
                            <span><strong>POST /whatsapp-wallet/pay/start</strong> — send order summary + amount; customer gets a WhatsApp message with a secure PIN link.</span>
                        </li>
                        <li class="flex gap-3">
                            <i class="fas fa-bell text-primary mt-1"></i>
                            <span>On success, your business is credited and <code class="bg-gray-100 px-1 rounded text-xs">payment.approved</code> hits your webhook.</span>
                        </li>
                        <li class="flex gap-3">
                            <i class="fas fa-lock text-primary mt-1"></i>
                            <span>No PIN-less debit API — customers always confirm on Checkout’s secure page.</span>
                        </li>
                    </ul>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('api-docs') }}#whatsapp-wallet" class="inline-flex items-center text-primary font-semibold hover:underline">
                            WhatsApp wallet API <i class="fas fa-arrow-right ml-2 text-sm"></i>
                        </a>
                        <a href="{{ route('business.register') }}" class="inline-flex items-center px-5 py-2.5 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium text-sm">
                            Create business account
                        </a>
                    </div>
                </div>
                <div class="code-block-dark">
                    <pre><code>POST /api/v1/whatsapp-wallet/pay/start
X-API-Key: pk_your_key

{
  "phone": "08012345678",
  "amount": 2500.00,
  "order_reference": "ORDER-123",
  "order_summary": "2x Jollof + delivery",
  "webhook_url": "https://your-app.com/hooks/checkout"
}</code></pre>
                </div>
            </div>
        </div>
    </section>

    {{-- vs gateway --}}
    <section class="py-14 bg-gray-50">
        <div class="max-w-container mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-gray-900 text-center mb-10">WhatsApp Wallet + payment gateway</h2>
            <div class="overflow-x-auto">
                <table class="w-full max-w-3xl mx-auto text-sm bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="text-left p-4 font-semibold text-gray-900"></th>
                            <th class="text-left p-4 font-semibold text-wa-dark">WhatsApp Wallet</th>
                            <th class="text-left p-4 font-semibold text-primary">Payment gateway</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr><td class="p-4 text-gray-600">Best for</td><td class="p-4">People paying people</td><td class="p-4">Websites &amp; WooCommerce stores</td></tr>
                        <tr><td class="p-4 text-gray-600">Interface</td><td class="p-4">WhatsApp chat + wallet app</td><td class="p-4">Checkout page, API, plugins</td></tr>
                        <tr><td class="p-4 text-gray-600">Receive money</td><td class="p-4">P2P, virtual account (Tier 2)</td><td class="p-4">Bank transfer to business account</td></tr>
                        <tr><td class="p-4 text-gray-600">Same platform</td><td class="p-4 col-span-2 text-center text-gray-800 font-medium" colspan="2">One CheckoutPay account — wallet, API, and dashboard together</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="text-center mt-8 text-gray-600 text-sm">
                Also need web checkout? See <a href="{{ route('products.index') }}" class="text-primary font-medium hover:underline">all products</a> or our <a href="{{ route('wordpress-plugin.index') }}" class="text-primary font-medium hover:underline">WooCommerce plugin</a>.
            </p>
        </div>
    </section>

    @include('partials.faq-section', [
        'category' => 'whatsapp-wallet',
        'title' => 'WhatsApp Wallet FAQs',
    ])

    {{-- CTA --}}
    <section class="py-14 bg-gradient-to-r from-wa-dark to-wa text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold mb-4">Your number. Their number. Send.</h2>
            <p class="text-green-100 mb-8 text-lg">The payment experience that meets people where they already are.</p>
            <div class="flex flex-col sm:flex-row justify-center gap-3">
                @if($waContact)
                    <a href="{{ $waContact }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 px-8 py-3.5 bg-white text-wa-dark rounded-lg font-semibold hover:bg-green-50">
                        <i class="fab fa-whatsapp text-xl"></i> Start on WhatsApp
                    </a>
                @endif
                <a href="{{ Wa::appUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center px-8 py-3.5 border-2 border-white/80 rounded-lg font-semibold hover:bg-white/10">
                    Wallet web app
                </a>
            </div>
            <p class="mt-6 text-sm text-green-100/90">
                Questions? <a href="{{ route('support.index') }}" class="underline font-medium text-white">Support</a>
                · <a href="{{ route('contact') }}" class="underline font-medium text-white">Contact</a>
            </p>
        </div>
    </section>

@endsection

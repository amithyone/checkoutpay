@props(['hero' => []])

@php
    use App\Support\MarketingAssets;
    use App\Support\MarketingPricing;

    $pricingText = MarketingPricing::snapshot()['pricing_text'];
    $demoPayCode = 'K7MX2';
    $heroImage = MarketingAssets::url('hero');
@endphp

<section id="hero-section" class="pt-8 pb-20 px-4 sm:px-6 lg:px-8 max-w-container mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
        <div class="space-y-6 md:space-y-8">
            @if(!empty($hero['badge_text']))
                <div class="badge-brand">
                    <span class="h-2 w-2 rounded-full bg-brand-primary animate-pulse"></span>
                    {{ $hero['badge_text'] }}
                </div>
            @else
                <div class="badge-brand">
                    <span class="h-2 w-2 rounded-full bg-brand-primary animate-pulse"></span>
                    Built for Nigerian businesses
                </div>
            @endif

            <h1 class="text-4xl md:text-5xl lg:text-[3.25rem] lg:leading-[1.1] font-extrabold text-midnight-deep tracking-tight">
                @if(!empty($hero['title_highlight']))
                    {{ $hero['title'] ?? 'Intelligent ' }}
                    <span class="text-brand-primary">{{ $hero['title_highlight'] }}</span>{{ $hero['title_suffix'] ?? '' }}
                @elseif(!empty($hero['title']))
                    {{ $hero['title'] }}
                @else
                    Intelligent <span class="text-brand-primary">Payment Gateway</span> for Nigerian Business
                @endif
            </h1>

            <p class="text-lg text-slate-600 leading-relaxed font-medium max-w-xl">
                @if(!empty($hero['description']))
                    {{ $hero['description'] }}
                @else
                    Accept bank transfers and WhatsApp Pay Code on one checkout — virtual accounts, automated matching, and real-time webhooks for Nigerian merchants.
                @endif
                @if(!empty($pricingText))
                    <span class="pricing-badge ml-1">{{ $pricingText }}</span>
                @endif
            </p>

            @if(!empty($hero['subtext']))
                <p class="text-sm text-slate-500 font-medium leading-relaxed max-w-xl">
                    {{ $hero['subtext'] }}
                </p>
            @endif

            <div class="flex flex-wrap gap-4 pt-2">
                <a href="{{ route('business.register') }}" class="btn-brand">
                    {{ $hero['cta_primary'] ?? 'Get started' }}
                    <i class="fas fa-arrow-right text-xs"></i>
                </a>
                <a href="#pricing" class="btn-brand-outline">
                    {{ $hero['cta_secondary'] ?? 'View pricing' }}
                </a>
            </div>

            <p class="pt-4 flex items-center gap-2 text-slate-500 text-xs font-bold uppercase tracking-wide">
                <i class="fas fa-shield-alt text-brand-electric"></i>
                Powered by METRAVON INNOVATION LTD
            </p>

            <div class="p-5 bg-surface-container-low rounded-2xl border border-slate-200/80 max-w-lg">
                <x-marketing.app-store-badges :compact="true" :show-web-fallback="false" />
            </div>
        </div>

        <div class="relative group" id="hero-visual">
            <div class="absolute -inset-4 bg-gradient-to-tr from-brand-primary/20 to-transparent blur-3xl opacity-50 group-hover:opacity-70 transition-opacity pointer-events-none"></div>
            <div class="relative transform group-hover:scale-[1.02] transition-transform duration-700">
                <img
                    src="{{ $heroImage }}"
                    alt="CheckoutPay payment success on mobile — Nigerian business professional"
                    class="w-full rounded-2xl shadow-premium object-cover aspect-[4/3] lg:aspect-auto"
                    loading="eager"
                    width="640"
                    height="480"
                >
                <button type="button" id="hero-playground-toggle" class="absolute bottom-4 right-4 glass-marketing px-4 py-2 rounded-xl text-xs font-bold text-midnight-deep hover:bg-white shadow-lg flex items-center gap-2">
                    <i class="fas fa-play text-brand-primary"></i> Try live playground
                </button>
            </div>
        </div>
    </div>

    {{-- Collapsible playground --}}
    <div id="hero-playground" class="hidden mt-12 max-w-3xl mx-auto">
        <div class="card-marketing p-6 md:p-8">
            <div class="flex gap-2 bg-surface-container-low p-1 rounded-xl mb-6">
                <button type="button" data-pay-rail="bank" class="pay-rail flex-1 py-2.5 rounded-lg text-xs font-bold bg-white shadow-sm text-midnight-deep">Bank VA</button>
                <button type="button" data-pay-rail="whatsapp" class="pay-rail flex-1 py-2.5 rounded-lg text-xs font-bold text-slate-500">WhatsApp Pay Code</button>
            </div>
            <div class="mb-4">
                <label for="hero-amount" class="text-xs font-bold text-slate-500 uppercase tracking-wide">Amount (NGN)</label>
                <input type="number" id="hero-amount" value="10000" min="100" step="100"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-3 text-lg font-bold text-midnight-deep focus:ring-2 focus:ring-brand-primary/30 focus:border-brand-primary outline-none">
            </div>
            <div id="rail-bank" class="rail-panel space-y-3 mb-4">
                <div class="bg-surface-container-low rounded-xl p-4 border border-slate-200/80">
                    <p class="text-[10px] font-bold text-slate-400 uppercase">Virtual account</p>
                    <p class="text-sm font-bold text-midnight-deep mt-1" id="hero-bank-name">Providus Bank</p>
                    <p class="text-xl font-black font-mono text-brand-primary mt-1" id="hero-account">9520148392</p>
                    <p class="text-xs text-slate-500 mt-1">CheckoutPay - METRAVON LTD</p>
                </div>
                <button type="button" id="hero-regen-account" class="text-xs font-semibold text-brand-primary hover:underline">
                    <i class="fas fa-sync-alt mr-1"></i> Regenerate demo account
                </button>
            </div>
            <div id="rail-whatsapp" class="rail-panel space-y-3 mb-4 hidden">
                <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-200">
                    <p class="text-[10px] font-bold text-emerald-700 uppercase"><i class="fab fa-whatsapp mr-1"></i> Send on WhatsApp</p>
                    <p class="text-lg font-mono font-black text-emerald-800 mt-2" id="hero-pay-message">PAY {{ $demoPayCode }}</p>
                    <p class="text-xs text-emerald-600 mt-2">Customer sends this message to pay from their Checkout wallet.</p>
                </div>
            </div>
            <div id="hero-pay-idle">
                <button type="button" id="hero-start-payment" class="btn-brand w-full justify-center">Proceed test payment</button>
                <p class="text-center text-[10px] text-slate-400 mt-2">Demo only — try the <a href="{{ route('checkout-demo.index') }}" class="text-brand-primary hover:underline">live checkout demo</a>.</p>
            </div>
            <div id="hero-pay-processing" class="hidden text-center py-8">
                <div class="inline-block w-10 h-10 border-4 border-brand-primary/30 border-t-brand-primary rounded-full animate-spin"></div>
                <p class="text-sm font-semibold text-slate-600 mt-4">Processing payment…</p>
            </div>
            <div id="hero-pay-success" class="hidden text-center py-6 space-y-4">
                <div class="w-14 h-14 mx-auto bg-success-green/15 rounded-full flex items-center justify-center">
                    <i class="fas fa-check text-2xl text-success-green"></i>
                </div>
                <p class="font-bold text-midnight-deep">Payment successful</p>
                <button type="button" id="hero-reset-payment" class="text-sm font-semibold text-brand-primary hover:underline">Reset demo</button>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script>
(function() {
    var toggle = document.getElementById('hero-playground-toggle');
    var panel = document.getElementById('hero-playground');
    if (toggle && panel) {
        toggle.addEventListener('click', function() {
            panel.classList.toggle('hidden');
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }

    var banks = ['Providus Bank', 'Wema Bank', 'Sterling Bank', 'Titan Trust Bank'];
    var codeChars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    var demoCode = '{{ $demoPayCode }}';

    function randomCode() {
        var c = '';
        for (var i = 0; i < 5; i++) c += codeChars.charAt(Math.floor(Math.random() * codeChars.length));
        return c;
    }

    function setRail(rail) {
        document.querySelectorAll('.pay-rail').forEach(function(btn) {
            var active = btn.getAttribute('data-pay-rail') === rail;
            btn.classList.toggle('bg-white', active);
            btn.classList.toggle('shadow-sm', active);
            btn.classList.toggle('text-midnight-deep', active);
            btn.classList.toggle('text-slate-500', !active);
        });
        document.getElementById('rail-bank').classList.toggle('hidden', rail !== 'bank');
        document.getElementById('rail-whatsapp').classList.toggle('hidden', rail !== 'whatsapp');
    }

    document.querySelectorAll('[data-pay-rail]').forEach(function(btn) {
        btn.addEventListener('click', function() { setRail(btn.getAttribute('data-pay-rail')); });
    });

    var amountInput = document.getElementById('hero-amount');
    var payMessage = document.getElementById('hero-pay-message');
    if (amountInput && payMessage) {
        amountInput.addEventListener('input', function() {
            demoCode = randomCode();
            payMessage.textContent = 'PAY ' + demoCode;
        });
    }

    var regenBtn = document.getElementById('hero-regen-account');
    if (regenBtn) {
        regenBtn.addEventListener('click', function() {
            document.getElementById('hero-bank-name').textContent = banks[Math.floor(Math.random() * banks.length)];
            document.getElementById('hero-account').textContent = String(Math.floor(1000000000 + Math.random() * 9000000000));
        });
    }

    var idle = document.getElementById('hero-pay-idle');
    var processing = document.getElementById('hero-pay-processing');
    var success = document.getElementById('hero-pay-success');
    function showStep(step) {
        idle.classList.toggle('hidden', step !== 'idle');
        processing.classList.toggle('hidden', step !== 'processing');
        success.classList.toggle('hidden', step !== 'success');
    }
    var startBtn = document.getElementById('hero-start-payment');
    var resetBtn = document.getElementById('hero-reset-payment');
    if (startBtn) startBtn.addEventListener('click', function() { showStep('processing'); setTimeout(function() { showStep('success'); }, 2200); });
    if (resetBtn) resetBtn.addEventListener('click', function() { showStep('idle'); });
})();
</script>
@endpush

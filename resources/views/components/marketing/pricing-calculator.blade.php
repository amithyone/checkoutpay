@props(['pricingSection' => []])

@php
    use App\Support\MarketingPricing;

    $pricingSnapshot = MarketingPricing::snapshot();
    $ratePct = $pricingSnapshot['rate_percentage'];
    $rateFixed = $pricingSnapshot['rate_fixed'];
    $examples = $pricingSnapshot['examples'];
    $included = $pricingSection['included'] ?? [];
    $calcPct = $pricingSnapshot['percentage'];
    $calcFixed = $pricingSnapshot['fixed'];
    $rateLine = $ratePct.' + '.$rateFixed;
@endphp

<section id="pricing" class="py-24 bg-midnight-deep text-white overflow-hidden relative">
    <div class="absolute top-0 right-0 w-1/2 h-full bg-gradient-to-l from-brand-primary/10 to-transparent pointer-events-none"></div>
    <div class="px-4 sm:px-6 lg:px-8 max-w-container mx-auto relative z-10">
        <div class="text-center mb-16 space-y-3">
            <h2 class="section-heading text-white">{{ $pricingSection['title'] ?? 'Pricing' }}</h2>
            <p class="text-white/60 font-medium">{{ $pricingSection['subtitle'] ?? 'Competitive rates. Clear charges. No surprises.' }}</p>
        </div>

        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-start">
            <div class="space-y-8">
                <div class="inline-block px-4 py-2 bg-white/5 rounded-full text-xs font-bold uppercase tracking-widest text-brand-electric">
                    {{ $pricingSection['plan_name'] ?? 'Pay As You Go' }}
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <span class="text-5xl sm:text-6xl lg:text-7xl font-extrabold tracking-tighter text-white">{{ $rateLine }}</span>
                </div>
                <p class="text-white/60 font-medium">{{ $pricingSection['rate_description'] ?? 'per successful transaction' }}</p>

                @if(count($included) > 0)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($included as $item)
                            <div class="flex items-center gap-2 text-sm text-white/80 font-medium">
                                <i class="fas fa-check-circle text-success-green text-sm shrink-0"></i>
                                <span>{{ is_array($item) ? ($item['title'] ?? $item) : $item }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach(['Unlimited transactions', 'API access', 'Hosted checkout', 'Real-time webhooks'] as $item)
                            <div class="flex items-center gap-2 text-sm text-white/80 font-medium">
                                <i class="fas fa-check-circle text-success-green text-sm"></i> {{ $item }}
                            </div>
                        @endforeach
                    </div>
                @endif

                <p class="text-xs text-white/40 pt-2">No setup fees. No monthly fees. Pay only for successful transactions.</p>
                <a href="{{ route('business.register') }}" class="btn-brand w-full justify-center bg-brand-electric hover:bg-brand-secondary shadow-brand">Get started</a>
                <a href="{{ route('pricing') }}" class="block text-center text-sm font-semibold text-white/60 hover:text-white transition-colors">Full pricing details</a>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl p-6 md:p-8">
                <h3 class="text-xl font-bold mb-6">Pricing Examples</h3>
                @if(count($examples) > 0)
                    <div class="space-y-1">
                        @foreach($examples as $ex)
                            <div class="flex justify-between items-center py-4 border-b border-white/10 last:border-0">
                                <span class="text-white/60 font-medium">Amount: {{ $ex['amount'] ?? '' }}</span>
                                <span class="font-bold">Fee: {{ $ex['fee'] ?? '' }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-8 pt-6 border-t border-white/10">
                    <label for="pricing-calc-amount" class="text-xs font-bold text-white/50 uppercase tracking-wide">Quick calculator</label>
                    <input type="number" id="pricing-calc-amount" value="10000" min="100" step="100"
                        class="mt-2 w-full rounded-xl border border-white/20 bg-white/5 px-4 py-3 text-lg font-bold text-white focus:ring-2 focus:ring-brand-electric outline-none">
                    <div class="flex justify-between items-center mt-4">
                        <span class="text-white/60 text-sm">Gateway fee</span>
                        <span class="text-2xl font-black text-brand-electric" id="calc-fee">—</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script>
(function() {
    var input = document.getElementById('pricing-calc-amount');
    var feeEl = document.getElementById('calc-fee');
    if (!input || !feeEl) return;
    var pct = {{ json_encode($calcPct) }};
    var fixed = {{ json_encode($calcFixed) }};
    function update() {
        var amount = Math.max(0, parseFloat(input.value) || 0);
        var fee = Math.round((amount * pct / 100) + fixed);
        feeEl.textContent = '₦' + fee.toLocaleString('en-NG');
    }
    input.addEventListener('input', update);
    update();
})();
</script>
@endpush

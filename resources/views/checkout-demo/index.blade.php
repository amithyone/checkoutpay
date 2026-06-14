@extends('layouts.marketing')

@section('title')
    @include('partials.marketing-head', ['seoPath' => '/checkout-demo'])
@endsection

@section('content')
    <x-marketing.product-hero
        badge="Hosted checkout"
        icon="fa-credit-card"
        title="Checkout Demo"
        subtitle="Experience our hosted checkout page. Enter payment details below to see how customers interact with our payment gateway."
    />

    <x-marketing.product-section bg="white">
        <div class="max-w-3xl mx-auto">
            <div class="card-marketing p-6 sm:p-8">
                <div class="mb-6 sm:mb-8">
                    <h2 class="text-xl sm:text-2xl font-bold text-midnight-deep mb-2">Try our checkout</h2>
                    <p class="text-slate-600 text-sm sm:text-base font-medium">Fill in the details below to test our hosted checkout page.</p>
                </div>

                <form id="demo-form" class="space-y-4 sm:space-y-6">
                    <div>
                        <label for="amount" class="block text-sm font-medium text-slate-700 mb-2">
                            Payment amount (₦) <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            id="amount"
                            name="amount"
                            step="0.01"
                            min="0.01"
                            value="5000"
                            required
                            class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition-colors"
                            placeholder="Enter amount"
                        >
                        <p class="mt-1 text-xs text-slate-500">Minimum amount: ₦0.01</p>
                    </div>

                    <div>
                        <label for="service" class="block text-sm font-medium text-slate-700 mb-2">
                            Service / product name (optional)
                        </label>
                        <input
                            type="text"
                            id="service"
                            name="service"
                            value="Demo Payment"
                            class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition-colors"
                            placeholder="e.g., Order #12345"
                        >
                    </div>

                    <div class="bg-brand-primary/5 border border-brand-primary/15 rounded-xl p-4">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-info-circle text-brand-primary mt-0.5" aria-hidden="true"></i>
                            <div class="text-sm text-slate-700">
                                <p class="font-semibold text-midnight-deep mb-1">Demo information</p>
                                <ul class="list-disc list-inside space-y-1 text-slate-600">
                                    <li>This is a test checkout — no real payment will be processed</li>
                                    <li>You will see the payment instructions page with account details</li>
                                    <li>The return URL will redirect back to this demo page</li>
                                    <li>Demo business: <strong>{{ $demoBusinessName ?? 'CheckoutPay Demo' }}</strong></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-brand w-full">
                        Launch checkout demo
                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </form>

                <div class="mt-8 sm:mt-10 pt-6 sm:pt-8 border-t border-slate-200">
                    <h3 class="text-lg sm:text-xl font-bold text-midnight-deep mb-4">How it works</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                        @foreach([
                            ['step' => '1', 'title' => 'Enter details', 'desc' => 'Fill in the payment amount and service name'],
                            ['step' => '2', 'title' => 'View checkout', 'desc' => 'See our hosted checkout page with payment instructions'],
                            ['step' => '3', 'title' => 'Payment details', 'desc' => 'Get account details and payment instructions'],
                        ] as $item)
                            <div class="text-center">
                                <div class="w-12 h-12 bg-brand-primary/10 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <span class="text-brand-primary font-bold text-xl">{{ $item['step'] }}</span>
                                </div>
                                <h4 class="font-semibold text-midnight-deep mb-2">{{ $item['title'] }}</h4>
                                <p class="text-sm text-slate-600">{{ $item['desc'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-8 sm:mt-10 pt-6 sm:pt-8 border-t border-slate-200">
                    <h3 class="text-lg sm:text-xl font-bold text-midnight-deep mb-4">Integration example</h3>
                    <div class="code-block-dark">
                        <pre><code>&lt;!-- Redirect to CheckoutPay hosted checkout --&gt;
&lt;a href="https://check-outpay.com/pay?
    business_id=YOUR_BUSINESS_ID&amp;
    amount=5000&amp;
    service=Order+123&amp;
    return_url=https://yourwebsite.com/success"&gt;
    Pay Now
&lt;/a&gt;</code></pre>
                    </div>
                    <p class="mt-3 text-sm text-slate-600 font-medium">
                        <i class="fas fa-lightbulb text-amber-500 mr-2" aria-hidden="true"></i>
                        Redirect customers to <code class="code-inline text-xs">/pay</code> with the required parameters.
                    </p>
                </div>
            </div>
        </div>
    </x-marketing.product-section>
@endsection

@push('scripts')
<script>
    document.getElementById('demo-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const amount = document.getElementById('amount').value;
        const service = document.getElementById('service').value;

        if (!amount || parseFloat(amount) < 0.01) {
            alert('Please enter a valid amount (minimum ₦0.01)');
            return;
        }

        const returnUrl = window.location.origin + '{{ route("checkout-demo.index") }}?demo=success';
        const checkoutUrl = new URL('{{ route("checkout.show") }}', window.location.origin);
        checkoutUrl.searchParams.set('business_id', '1');
        checkoutUrl.searchParams.set('amount', amount);
        if (service) {
            checkoutUrl.searchParams.set('service', service);
        }
        checkoutUrl.searchParams.set('return_url', returnUrl);
        checkoutUrl.searchParams.set('cancel_url', returnUrl);

        window.location.href = checkoutUrl.toString();
    });

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('demo') === 'success') {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'fixed top-20 left-1/2 transform -translate-x-1/2 bg-success-green text-white px-6 py-3 rounded-xl shadow-premium z-50';
        alertDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Demo completed! This is how customers would be redirected back to your site.';
        document.body.appendChild(alertDiv);
        setTimeout(function() { alertDiv.remove(); }, 5000);
    }
</script>
@endpush

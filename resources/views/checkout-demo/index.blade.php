<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Demo - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#3C50E0' },
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-white">
    @include('partials.nav')

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-primary/10 via-white to-primary/5 py-12 sm:py-16 md:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-12">
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-gray-900 mb-4 sm:mb-6">Checkout Demo</h1>
                <p class="text-base sm:text-lg md:text-xl text-gray-600 mb-6 sm:mb-8 max-w-3xl mx-auto">
                    Experience our hosted checkout page. Enter payment details below to see how customers interact with our payment gateway.
                </p>
            </div>
        </div>
    </section>

    <!-- Demo Form Section -->
    <section class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8">
                <div class="mb-6 sm:mb-8">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2">Try Our Checkout</h2>
                    <p class="text-gray-600 text-sm sm:text-base">Fill in the details below to test our hosted checkout page</p>
                </div>

                <form id="demo-form" class="space-y-4 sm:space-y-6">
                    <!-- Amount -->
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                            Payment Amount (₦) <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="number" 
                            id="amount" 
                            name="amount" 
                            step="0.01"
                            min="0.01"
                            value="5000"
                            required
                            class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                            placeholder="Enter amount"
                        >
                        <p class="mt-1 text-xs text-gray-500">Minimum amount: ₦0.01</p>
                    </div>

                    <!-- Service/Product Name -->
                    <div>
                        <label for="service" class="block text-sm font-medium text-gray-700 mb-2">
                            Service/Product Name (Optional)
                        </label>
                        <input 
                            type="text" 
                            id="service" 
                            name="service"
                            value="Demo Payment"
                            class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                            placeholder="e.g., Order #12345"
                        >
                    </div>

                <!-- Info Box -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-600 mt-0.5 mr-3"></i>
                        <div class="text-sm text-blue-800">
                            <p class="font-semibold mb-1">Demo Information</p>
                            <ul class="list-disc list-inside space-y-1 text-blue-700">
                                <li>This is a test checkout - no real payment will be processed</li>
                                <li>You'll see the payment instructions page with account details</li>
                                <li>The return URL will redirect back to this demo page</li>
                                <li>Use any name for testing purposes</li>
                                <li>Demo Business: <strong>{{ $demoBusinessName ?? 'CheckoutPay Demo' }}</strong></li>
                            </ul>
                        </div>
                    </div>
                </div>

                    <!-- Submit Button -->
                    <button 
                        type="submit" 
                        class="w-full bg-primary text-white py-3 sm:py-4 px-6 rounded-lg font-medium hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-colors flex items-center justify-center text-base sm:text-lg"
                    >
                        <span>Launch Checkout Demo</span>
                        <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </form>

                <!-- How It Works -->
                <div class="mt-8 sm:mt-10 pt-6 sm:pt-8 border-t border-gray-200">
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-4">How It Works</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-3">
                                <span class="text-primary font-bold text-xl">1</span>
                            </div>
                            <h4 class="font-semibold text-gray-900 mb-2">Enter Details</h4>
                            <p class="text-sm text-gray-600">Fill in the payment amount and service name</p>
                        </div>
                        <div class="text-center">
                            <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-3">
                                <span class="text-primary font-bold text-xl">2</span>
                            </div>
                            <h4 class="font-semibold text-gray-900 mb-2">View Checkout</h4>
                            <p class="text-sm text-gray-600">See our hosted checkout page with payment instructions</p>
                        </div>
                        <div class="text-center">
                            <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-3">
                                <span class="text-primary font-bold text-xl">3</span>
                            </div>
                            <h4 class="font-semibold text-gray-900 mb-2">Payment Details</h4>
                            <p class="text-sm text-gray-600">Get account details and payment instructions</p>
                        </div>
                    </div>
                </div>

                <!-- Integration Code Example -->
                <div class="mt-8 sm:mt-10 pt-6 sm:pt-8 border-t border-gray-200">
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-4">Integration Example</h3>
                    <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                        <pre class="text-green-400 text-xs sm:text-sm"><code>&lt;!-- Redirect to CheckoutPay hosted checkout --&gt;
&lt;a href="https://check-outpay.com/pay?
    business_id=YOUR_BUSINESS_ID&amp;
    amount=5000&amp;
    service=Order+123&amp;
    return_url=https://yourwebsite.com/success"&gt;
    Pay Now
&lt;/a&gt;</code></pre>
                    </div>
                    <p class="mt-3 text-sm text-gray-600">
                        <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
                        Simply redirect customers to <code class="bg-gray-100 px-2 py-1 rounded text-xs">/pay</code> with the required parameters
                    </p>
                </div>
            </div>
        </div>
    </section>

    @include('partials.footer')

    <script>
        document.getElementById('demo-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const amount = document.getElementById('amount').value;
            const service = document.getElementById('service').value;
            
            if (!amount || parseFloat(amount) < 0.01) {
                alert('Please enter a valid amount (minimum ₦0.01)');
                return;
            }

            // Get demo business ID - we'll use the first active business or create a demo
            const returnUrl = window.location.origin + '{{ route("checkout-demo.index") }}?demo=success';
            
            // Build checkout URL - Use business ID 1 (HGEOV)
            const checkoutUrl = new URL('{{ route("checkout.show") }}', window.location.origin);
            checkoutUrl.searchParams.set('business_id', '1');
            checkoutUrl.searchParams.set('amount', amount);
            if (service) {
                checkoutUrl.searchParams.set('service', service);
            }
            checkoutUrl.searchParams.set('return_url', returnUrl);
            checkoutUrl.searchParams.set('cancel_url', returnUrl);

            // Redirect to checkout
            window.location.href = checkoutUrl.toString();
        });

        // Check if returning from demo
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('demo') === 'success') {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'fixed top-20 left-1/2 transform -translate-x-1/2 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
            alertDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Demo completed! This is how customers would be redirected back to your site.';
            document.body.appendChild(alertDiv);
            setTimeout(() => alertDiv.remove(), 5000);
        }
    </script>
</body>
</html>

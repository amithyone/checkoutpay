<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developers - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
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
    <!-- Navigation -->
    @include('partials.nav')

    <!-- Hero -->
    <section class="bg-gradient-to-br from-primary/10 via-white to-primary/5 py-12 sm:py-16 md:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-gray-900 mb-4 sm:mb-6">Developer Hub</h1>
                <p class="text-base sm:text-lg md:text-xl text-gray-600 mb-6 sm:mb-8 max-w-3xl mx-auto">
                    Everything you need to integrate CheckoutPay into your application. API reference, webhooks, testing tools, and more.
                </p>
            </div>
        </div>
    </section>

    <!-- API Reference -->
    <section id="api-reference" class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-8 text-center">API Reference</h2>
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-4">Base URL</h3>
                <div class="bg-gray-900 rounded-lg p-4 mb-6">
                    <code class="text-green-400 text-sm">https://check-outpay.com/api/v1</code>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Authentication</h3>
                <p class="text-gray-600 mb-4">All API requests require an API key in the header:</p>
                <div class="bg-gray-900 rounded-lg p-4 mb-6">
                    <code class="text-green-400 text-sm">X-API-Key: pk_your_api_key_here</code>
                </div>
                <a href="{{ route('business.api-documentation.index') }}" class="inline-flex items-center px-6 py-3 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium">
                    View Complete API Documentation
                    <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Webhooks -->
    <section id="webhooks" class="py-12 sm:py-16 md:py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-8 text-center">Webhooks</h2>
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8">
                <p class="text-gray-600 mb-6">Receive real-time notifications when payment status changes. Configure your webhook URL in your dashboard.</p>
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <h4 class="font-semibold text-gray-900 mb-2">Webhook Events</h4>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li><code class="bg-white px-2 py-1 rounded">payment.approved</code> - Payment verified and approved</li>
                        <li><code class="bg-white px-2 py-1 rounded">payment.rejected</code> - Payment rejected or expired</li>
                    </ul>
                </div>
                <a href="{{ route('business.api-documentation.index') }}#webhooks" class="text-primary hover:text-primary/80 font-medium">
                    Learn More About Webhooks <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Testing -->
    <section id="testing" class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-8 text-center">Testing</h2>
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-4">Test Mode</h3>
                <p class="text-gray-600 mb-4">Use test API keys to test your integration without processing real payments.</p>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                    <p class="text-sm text-yellow-800"><strong>Note:</strong> Test mode uses the same API endpoints but with test credentials. No real payments will be processed.</p>
                </div>
            </div>
        </div>
    </section>

    @include('partials.footer')
</body>
</html>

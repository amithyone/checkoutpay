<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
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
            <div class="text-center">
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-gray-900 mb-4 sm:mb-6">
                    Developer Resources & Documentation
                </h1>
                <p class="text-base sm:text-lg md:text-xl text-gray-600 mb-6 sm:mb-8 max-w-3xl mx-auto">
                    Everything you need to integrate CheckoutPay into your application. Comprehensive guides, code examples, and SDKs to get you started quickly.
                </p>
            </div>
        </div>
    </section>

    <!-- Documentation Section -->
    <section id="documentation" class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-4">API Documentation</h2>
                <p class="text-lg text-gray-600">Complete reference for integrating with CheckoutPay API</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-book text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Complete API Reference</h3>
                    <p class="text-gray-600 mb-4">
                        Detailed documentation covering all API endpoints, request/response formats, authentication, and error handling.
                    </p>
                    <ul class="space-y-2 mb-6 text-sm text-gray-600">
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                            <span>Payment request creation</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                            <span>Transaction status checking</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                            <span>Webhook configuration</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                            <span>Charge management</span>
                        </li>
                    </ul>
                    <a href="{{ route('business.api-documentation.index') }}" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium">
                        View Full Documentation
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>

                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-code text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Code Examples</h3>
                    <p class="text-gray-600 mb-4">
                        Ready-to-use code examples in multiple programming languages to help you integrate quickly.
                    </p>
                    <div class="grid grid-cols-2 gap-3 mb-6">
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <i class="fab fa-php text-2xl text-gray-600 mb-2"></i>
                            <p class="text-sm font-medium text-gray-700">PHP</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <i class="fab fa-js text-2xl text-gray-600 mb-2"></i>
                            <p class="text-sm font-medium text-gray-700">JavaScript</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <i class="fab fa-python text-2xl text-gray-600 mb-2"></i>
                            <p class="text-sm font-medium text-gray-700">Python</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <i class="fab fa-node text-2xl text-gray-600 mb-2"></i>
                            <p class="text-sm font-medium text-gray-700">Node.js</p>
                        </div>
                    </div>
                    <a href="{{ route('developers.index') }}#examples" class="inline-flex items-center px-4 py-2 border-2 border-primary text-primary rounded-lg hover:bg-primary/5 font-medium">
                        View Examples
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Integration Guides -->
    <section id="guides" class="py-12 sm:py-16 md:py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-4">Integration Guides</h2>
                <p class="text-lg text-gray-600">Step-by-step guides for different integration methods</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
                <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-plug text-primary text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">API Integration</h3>
                    <p class="text-gray-600 text-sm mb-4">Integrate payments directly into your application using our REST API.</p>
                    <a href="{{ route('developers.index') }}#api-integration" class="text-primary hover:text-primary/80 text-sm font-medium">
                        View Guide <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div id="hosted-checkout" class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-globe text-primary text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Hosted Checkout</h3>
                    <p class="text-gray-600 text-sm mb-4">Redirect customers to our secure hosted payment page - no coding required.</p>
                    <a href="{{ route('developers.index') }}#hosted-checkout" class="text-primary hover:text-primary/80 text-sm font-medium">
                        View Guide <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div id="wordpress" class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fab fa-wordpress text-purple-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">WordPress Plugin</h3>
                    <p class="text-gray-600 text-sm mb-4">Install our WooCommerce plugin and start accepting payments in minutes.</p>
                    <a href="{{ route('products.index') }}#wordpress-plugin" class="text-primary hover:text-primary/80 text-sm font-medium">
                        View Guide <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- SDKs & Libraries -->
    <section id="sdk" class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-4">SDKs & Libraries</h2>
                <p class="text-lg text-gray-600">Official and community-maintained SDKs for popular platforms</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl border border-purple-200 p-6 sm:p-8">
                    <div class="flex items-center mb-4">
                        <i class="fab fa-wordpress text-purple-600 text-3xl mr-4"></i>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">WordPress Plugin</h3>
                            <p class="text-sm text-gray-600">Official WooCommerce integration</p>
                        </div>
                    </div>
                    <p class="text-gray-700 mb-4">Seamlessly integrate CheckoutPay with your WooCommerce store. One-click installation with automatic charge calculation.</p>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="{{ asset('downloads/checkoutpay-gateway.zip') }}" download class="inline-flex items-center justify-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium">
                            <i class="fas fa-download mr-2"></i> Download
                        </a>
                        <a href="{{ route('products.index') }}#wordpress-plugin" class="inline-flex items-center justify-center px-4 py-2 border-2 border-purple-600 text-purple-600 rounded-lg hover:bg-purple-50 font-medium">
                            Documentation
                        </a>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-xl border border-gray-200 p-6 sm:p-8">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-code text-primary text-3xl mr-4"></i>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">REST API</h3>
                            <p class="text-sm text-gray-600">Universal API for all platforms</p>
                        </div>
                    </div>
                    <p class="text-gray-700 mb-4">Use our RESTful API with any programming language. Comprehensive documentation with examples in PHP, JavaScript, Python, and more.</p>
                    <a href="{{ route('business.api-documentation.index') }}" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium">
                        View API Docs
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Code Examples -->
    <section id="examples" class="py-12 sm:py-16 md:py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-4">Quick Start Examples</h2>
                <p class="text-lg text-gray-600">Get started in minutes with these code examples</p>
            </div>
            <div class="space-y-6">
                <!-- PHP Example -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-900 px-4 py-2 flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fab fa-php text-purple-400 text-xl mr-2"></i>
                            <span class="text-white font-medium">PHP (Laravel)</span>
                        </div>
                        <button onclick="copyCode('php-example')" class="text-gray-400 hover:text-white">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="p-4 overflow-x-auto">
                        <pre id="php-example" class="text-xs text-gray-800"><code>use Illuminate\Support\Facades\Http;

$response = Http::withHeaders([
    'X-API-Key' => 'your_api_key',
    'Content-Type' => 'application/json',
])->post('https://check-outpay.com/api/v1/payment-request', [
    'name' => 'John Doe',
    'amount' => 5000.00,
    'service' => 'ORDER-123',
    'webhook_url' => 'https://yourwebsite.com/webhook',
]);

$data = $response->json();
$accountNumber = $data['data']['account_number'];
$transactionId = $data['data']['transaction_id'];</code></pre>
                    </div>
                </div>

                <!-- JavaScript Example -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-900 px-4 py-2 flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fab fa-js text-yellow-400 text-xl mr-2"></i>
                            <span class="text-white font-medium">JavaScript (Node.js)</span>
                        </div>
                        <button onclick="copyCode('js-example')" class="text-gray-400 hover:text-white">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="p-4 overflow-x-auto">
                        <pre id="js-example" class="text-xs text-gray-800"><code>const axios = require('axios');

const response = await axios.post(
    'https://check-outpay.com/api/v1/payment-request',
    {
        name: 'John Doe',
        amount: 5000.00,
        service: 'ORDER-123',
        webhook_url: 'https://yourwebsite.com/webhook'
    },
    {
        headers: {
            'X-API-Key': 'your_api_key',
            'Content-Type': 'application/json'
        }
    }
);

const { account_number, transaction_id } = response.data.data;</code></pre>
                    </div>
                </div>

                <!-- Python Example -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-900 px-4 py-2 flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fab fa-python text-blue-400 text-xl mr-2"></i>
                            <span class="text-white font-medium">Python</span>
                        </div>
                        <button onclick="copyCode('python-example')" class="text-gray-400 hover:text-white">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="p-4 overflow-x-auto">
                        <pre id="python-example" class="text-xs text-gray-800"><code>import requests

response = requests.post(
    'https://check-outpay.com/api/v1/payment-request',
    json={
        'name': 'John Doe',
        'amount': 5000.00,
        'service': 'ORDER-123',
        'webhook_url': 'https://yourwebsite.com/webhook'
    },
    headers={
        'X-API-Key': 'your_api_key',
        'Content-Type': 'application/json'
    }
)

data = response.json()
account_number = data['data']['account_number']
transaction_id = data['data']['transaction_id']</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @include('partials.footer')

    <script>
        function copyCode(elementId) {
            const codeElement = document.getElementById(elementId);
            const text = codeElement.textContent || codeElement.innerText;
            navigator.clipboard.writeText(text).then(() => {
                alert('Code copied to clipboard!');
            });
        }
        
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        mobileMenuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    </script>
</body>
</html>

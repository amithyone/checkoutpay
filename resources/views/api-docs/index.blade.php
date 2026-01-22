<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    <meta name="description" content="Complete API integration guide for CheckoutPay payment gateway. Learn how to integrate payments, webhooks, and manage transactions with our RESTful API.">
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
    <style>
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        code {
            font-family: 'Courier New', monospace;
        }
        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem;
            overflow-x: auto;
        }
        .code-block code {
            color: #e2e8f0;
        }
        .endpoint-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-get { background: #10b981; color: white; }
        .badge-post { background: #3b82f6; color: white; }
        .badge-put { background: #f59e0b; color: white; }
        .badge-delete { background: #ef4444; color: white; }
    </style>
</head>
<body class="bg-gray-50">
    @include('partials.nav')

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-primary/10 via-white to-primary/5 py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-4xl sm:text-5xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-code mr-3 text-primary"></i>API Documentation
                </h1>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Complete integration guide for CheckoutPay Payment Gateway API. Build powerful payment solutions with our RESTful API.
                </p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <!-- Sidebar Navigation -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sticky top-20">
                        <h3 class="font-semibold text-gray-900 mb-4">Quick Navigation</h3>
                        <nav class="space-y-2">
                            <a href="#getting-started" class="block text-sm text-gray-700 hover:text-primary py-2">Getting Started</a>
                            <a href="#authentication" class="block text-sm text-gray-700 hover:text-primary py-2">Authentication</a>
                            <a href="#endpoints" class="block text-sm text-gray-700 hover:text-primary py-2">API Endpoints</a>
                            <a href="#payments" class="block text-sm text-gray-700 hover:text-primary py-2 ml-4">Payments</a>
                            <a href="#withdrawals" class="block text-sm text-gray-700 hover:text-primary py-2 ml-4">Withdrawals</a>
                            <a href="#webhooks" class="block text-sm text-gray-700 hover:text-primary py-2">Webhooks</a>
                            <a href="#code-examples" class="block text-sm text-gray-700 hover:text-primary py-2">Code Examples</a>
                            <a href="#error-handling" class="block text-sm text-gray-700 hover:text-primary py-2">Error Handling</a>
                            <a href="#rate-limits" class="block text-sm text-gray-700 hover:text-primary py-2">Rate Limits</a>
                        </nav>
                    </div>
                </div>

                <!-- Main Documentation -->
                <div class="lg:col-span-3 space-y-8">
                    <!-- Getting Started -->
                    <div id="getting-started" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-rocket text-primary mr-2"></i>Getting Started
                        </h2>
                        
                        <div class="space-y-6">
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">1. Sign Up & Get Your API Key</h3>
                                <p class="text-gray-700 mb-4">Create an account and get your API key from the dashboard. Your API key is required for all authenticated requests.</p>
                                <a href="{{ route('business.register') }}" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium">
                                    <i class="fas fa-user-plus mr-2"></i> Sign Up Now
                                </a>
                            </div>

                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">2. Base URL</h3>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <code class="text-sm text-gray-900">{{ url('/api/v1') }}</code>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">3. Request Format</h3>
                                <p class="text-gray-700 mb-3">All API requests must:</p>
                                <ul class="list-disc list-inside text-gray-700 space-y-2 mb-4">
                                    <li>Use HTTPS</li>
                                    <li>Include <code class="bg-gray-100 px-2 py-1 rounded">X-API-Key</code> header for authenticated endpoints</li>
                                    <li>Send JSON data in request body (for POST/PUT requests)</li>
                                    <li>Use <code class="bg-gray-100 px-2 py-1 rounded">Content-Type: application/json</code> header</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Authentication -->
                    <div id="authentication" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-key text-primary mr-2"></i>Authentication
                        </h2>
                        
                        <p class="text-gray-700 mb-4">All authenticated API requests require your API key in the request header.</p>
                        
                        <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto mb-4">
                            <pre class="text-sm text-gray-100"><code>X-API-Key: pk_your_api_key_here</code></pre>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>Security:</strong> Keep your API key secure and never expose it in client-side code. Store it securely on your server.
                            </p>
                        </div>
                    </div>

                    <!-- API Endpoints -->
                    <div id="endpoints" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-6">
                            <i class="fas fa-plug text-primary mr-2"></i>API Endpoints
                        </h2>

                        <!-- Payments Section -->
                        <div id="payments" class="mb-8">
                            <h3 class="text-2xl font-semibold text-gray-900 mb-4">Payments</h3>

                            <!-- Create Payment Request -->
                            <div class="mb-6 border-l-4 border-blue-500 pl-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="endpoint-badge badge-post">POST</span>
                                    <code class="text-lg font-mono text-gray-900">/payment-request</code>
                                </div>
                                <p class="text-gray-700 mb-4">Create a new payment request. Returns account details for the customer to make payment.</p>

                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-900 mb-2">Request Body</h4>
                                    <div class="code-block">
                                        <pre><code>{
  "amount": 5000.00,
  "payer_name": "John Doe",
  "bank": "GTBank",
  "webhook_url": "https://yourwebsite.com/webhook/payment-status",
  "service": "Product Purchase",
  "transaction_id": "TXN-1234567890",
  "business_website_id": 1,
  "website_url": "https://yourwebsite.com"
}</code></pre>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-900 mb-2">Request Parameters</h4>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-gray-700 font-semibold">Parameter</th>
                                                    <th class="px-4 py-3 text-left text-gray-700 font-semibold">Type</th>
                                                    <th class="px-4 py-3 text-left text-gray-700 font-semibold">Required</th>
                                                    <th class="px-4 py-3 text-left text-gray-700 font-semibold">Description</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">amount</td>
                                                    <td class="px-4 py-3 text-gray-700">decimal</td>
                                                    <td class="px-4 py-3 text-gray-700">Yes</td>
                                                    <td class="px-4 py-3 text-gray-700">Payment amount (minimum 0.01)</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">payer_name</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700"><span class="text-red-600 font-semibold">Yes</span></td>
                                                    <td class="px-4 py-3 text-gray-700">Customer's name (required to get account number)</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">name</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700"><span class="text-red-600 font-semibold">Yes*</span></td>
                                                    <td class="px-4 py-3 text-gray-700">Alternative to payer_name (either 'name' or 'payer_name' is required)</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">bank</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">No</td>
                                                    <td class="px-4 py-3 text-gray-700">Customer's bank name</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">webhook_url</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">Yes</td>
                                                    <td class="px-4 py-3 text-gray-700">URL to receive payment notifications (must be from approved website)</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">service</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">No</td>
                                                    <td class="px-4 py-3 text-gray-700">Description of the service/product</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">transaction_id</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">No</td>
                                                    <td class="px-4 py-3 text-gray-700">Your unique transaction ID (auto-generated if not provided)</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">business_website_id</td>
                                                    <td class="px-4 py-3 text-gray-700">integer</td>
                                                    <td class="px-4 py-3 text-gray-700">No</td>
                                                    <td class="px-4 py-3 text-gray-700">ID of your approved website (for website-specific webhooks)</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">website_url</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">No</td>
                                                    <td class="px-4 py-3 text-gray-700">Your website URL (for website identification)</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-900 mb-2">Response (201 Created)</h4>
                                    <div class="code-block">
                                        <pre><code>{
  "success": true,
  "message": "Payment request created successfully",
  "data": {
    "transaction_id": "TXN-1234567890",
    "amount": 5000.00,
    "payer_name": "John Doe",
    "account_number": "0123456789",
    "account_name": "Your Business Name",
    "bank_name": "GTBank",
    "status": "pending",
    "expires_at": "2024-01-15T12:00:00Z",
    "created_at": "2024-01-15T10:00:00Z",
    "charges": {
      "percentage": 50.00,
      "fixed": 50.00,
      "total": 100.00,
      "paid_by_customer": false,
      "amount_to_pay": 5000.00,
      "business_receives": 4900.00
    },
    "website": {
      "id": 1,
      "url": "https://yourwebsite.com"
    }
  }
}</code></pre>
                                    </div>
                                </div>
                            </div>

                            <!-- Get Payment -->
                            <div class="mb-6 border-l-4 border-green-500 pl-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="endpoint-badge badge-get">GET</span>
                                    <code class="text-lg font-mono text-gray-900">/payment/{transactionId}</code>
                                </div>
                                <p class="text-gray-700 mb-4">Retrieve payment details by transaction ID.</p>

                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-900 mb-2">Response (200 OK)</h4>
                                    <div class="code-block">
                                        <pre><code>{
  "success": true,
  "data": {
    "transaction_id": "TXN-1234567890",
    "amount": 5000.00,
    "payer_name": "John Doe",
    "bank": "GTBank",
    "account_number": "0123456789",
    "account_name": "Your Business Name",
    "bank_name": "GTBank",
    "status": "approved",
    "webhook_url": "https://yourwebsite.com/webhook/payment-status",
    "expires_at": "2024-01-15T12:00:00Z",
    "matched_at": "2024-01-15T10:30:00Z",
    "approved_at": "2024-01-15T10:35:00Z",
    "created_at": "2024-01-15T10:00:00Z",
    "updated_at": "2024-01-15T10:35:00Z",
    "charges": {
      "percentage": 50.00,
      "fixed": 50.00,
      "total": 100.00,
      "paid_by_customer": false,
      "business_receives": 4900.00
    },
    "website": {
      "id": 1,
      "url": "https://yourwebsite.com"
    }
  }
}</code></pre>
                                    </div>
                                </div>
                            </div>

                            <!-- List Payments -->
                            <div class="mb-6 border-l-4 border-green-500 pl-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="endpoint-badge badge-get">GET</span>
                                    <code class="text-lg font-mono text-gray-900">/payments</code>
                                </div>
                                <p class="text-gray-700 mb-4">List all payments for your business with optional filters.</p>

                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-900 mb-2">Query Parameters</h4>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-gray-700 font-semibold">Parameter</th>
                                                    <th class="px-4 py-3 text-left text-gray-700 font-semibold">Type</th>
                                                    <th class="px-4 py-3 text-left text-gray-700 font-semibold">Description</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">status</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">Filter by status: pending, approved, rejected</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">from_date</td>
                                                    <td class="px-4 py-3 text-gray-700">date</td>
                                                    <td class="px-4 py-3 text-gray-700">Filter payments from this date (YYYY-MM-DD)</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">to_date</td>
                                                    <td class="px-4 py-3 text-gray-700">date</td>
                                                    <td class="px-4 py-3 text-gray-700">Filter payments until this date (YYYY-MM-DD)</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">website_id</td>
                                                    <td class="px-4 py-3 text-gray-700">integer</td>
                                                    <td class="px-4 py-3 text-gray-700">Filter by website ID</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">per_page</td>
                                                    <td class="px-4 py-3 text-gray-700">integer</td>
                                                    <td class="px-4 py-3 text-gray-700">Number of results per page (default: 15)</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-900 mb-2">Example Request</h4>
                                    <div class="code-block">
                                        <pre><code>GET {{ url('/api/v1/payments?status=approved&from_date=2024-01-01&per_page=20') }}
X-API-Key: pk_your_api_key_here</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Withdrawals Section -->
                        <div id="withdrawals" class="mb-8">
                            <h3 class="text-2xl font-semibold text-gray-900 mb-4 mt-8">Withdrawals</h3>

                            <!-- Create Withdrawal -->
                            <div class="mb-6 border-l-4 border-blue-500 pl-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="endpoint-badge badge-post">POST</span>
                                    <code class="text-lg font-mono text-gray-900">/withdrawal</code>
                                </div>
                                <p class="text-gray-700 mb-4">Request a withdrawal from your account balance.</p>

                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-900 mb-2">Request Body</h4>
                                    <div class="code-block">
                                        <pre><code>{
  "amount": 10000.00,
  "account_number": "0123456789",
  "account_name": "Your Name",
  "bank_name": "GTBank"
}</code></pre>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-900 mb-2">Request Parameters</h4>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-gray-700 font-semibold">Parameter</th>
                                                    <th class="px-4 py-3 text-left text-gray-700 font-semibold">Type</th>
                                                    <th class="px-4 py-3 text-left text-gray-700 font-semibold">Required</th>
                                                    <th class="px-4 py-3 text-left text-gray-700 font-semibold">Description</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">amount</td>
                                                    <td class="px-4 py-3 text-gray-700">decimal</td>
                                                    <td class="px-4 py-3 text-gray-700">Yes</td>
                                                    <td class="px-4 py-3 text-gray-700">Withdrawal amount (must not exceed balance)</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">account_number</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">Yes</td>
                                                    <td class="px-4 py-3 text-gray-700">Bank account number (10 digits)</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">account_name</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">Yes</td>
                                                    <td class="px-4 py-3 text-gray-700">Account holder name</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">bank_name</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">Yes</td>
                                                    <td class="px-4 py-3 text-gray-700">Bank name</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- List Withdrawals -->
                            <div class="mb-6 border-l-4 border-green-500 pl-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="endpoint-badge badge-get">GET</span>
                                    <code class="text-lg font-mono text-gray-900">/withdrawals</code>
                                </div>
                                <p class="text-gray-700 mb-4">List all withdrawal requests with optional status filter.</p>
                            </div>

                            <!-- Get Balance -->
                            <div class="mb-6 border-l-4 border-green-500 pl-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="endpoint-badge badge-get">GET</span>
                                    <code class="text-lg font-mono text-gray-900">/balance</code>
                                </div>
                                <p class="text-gray-700 mb-4">Get your current account balance.</p>

                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-900 mb-2">Response (200 OK)</h4>
                                    <div class="code-block">
                                        <pre><code>{
  "success": true,
  "data": {
    "balance": 50000.00,
    "currency": "NGN"
  }
}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Webhooks Section -->
                    <div id="webhooks" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-bell text-primary mr-2"></i>Webhooks
                        </h2>

                        <div class="space-y-6">
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">Overview</h3>
                                <p class="text-gray-700 mb-4">
                                    Webhooks allow you to receive real-time notifications when payment events occur. You can set webhook URLs at the business level or per-website level for more granular control.
                                </p>
                            </div>

                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">Webhook Priority</h3>
                                <p class="text-gray-700 mb-3">Webhooks are sent in the following priority order:</p>
                                <ol class="list-decimal list-inside text-gray-700 space-y-2 mb-4">
                                    <li><strong>Website-specific webhook URL</strong> (if payment is associated with a website that has a webhook URL)</li>
                                    <li><strong>Payment webhook URL</strong> (from the payment request)</li>
                                    <li><strong>Business webhook URL</strong> (fallback)</li>
                                </ol>
                            </div>

                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">Webhook Payload</h3>
                                <p class="text-gray-700 mb-4">When a payment is approved, you'll receive a POST request to your webhook URL with the following payload:</p>
                                <div class="code-block">
                                    <pre><code>{
  "event": "payment.approved",
  "transaction_id": "TXN-1234567890",
  "status": "approved",
  "amount": 5000.00,
  "received_amount": 5000.00,
  "payer_name": "John Doe",
  "bank": "GTBank",
  "payer_account_number": "0123456789",
  "account_number": "0987654321",
  "account_details": {
    "account_name": "Your Business Name",
    "bank_name": "GTBank"
  },
  "is_mismatch": false,
  "mismatch_reason": null,
  "name_mismatch": false,
  "name_similarity_percent": 100,
  "matched_at": "2024-01-15T10:30:00Z",
  "approved_at": "2024-01-15T10:35:00Z",
  "created_at": "2024-01-15T10:00:00Z",
  "timestamp": "2024-01-15T10:35:00Z",
  "website": {
    "id": 1,
    "url": "https://yourwebsite.com"
  },
  "charges": {
    "percentage": 50.00,
    "fixed": 50.00,
    "total": 100.00,
    "paid_by_customer": false,
    "business_receives": 4900.00
  },
  "email": {
    "subject": "Credit Alert",
    "from": "noreply@gtbank.com",
    "date": "2024-01-15T10:30:00Z"
  }
}</code></pre>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">Webhook Security</h3>
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <p class="text-sm text-yellow-800 mb-2">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        <strong>Important:</strong> Always validate webhook requests on your server. Consider implementing:
                                    </p>
                                    <ul class="list-disc list-inside text-sm text-yellow-800 space-y-1 ml-4">
                                        <li>IP whitelisting (if possible)</li>
                                        <li>Request signature verification (coming soon)</li>
                                        <li>Idempotency checks using transaction_id</li>
                                    </ul>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">Setting Webhook URLs</h3>
                                <p class="text-gray-700 mb-3">You can set webhook URLs in two ways:</p>
                                <ol class="list-decimal list-inside text-gray-700 space-y-2 mb-4">
                                    <li><strong>Per-Website:</strong> Set a webhook URL for each approved website in your dashboard. This allows different webhook endpoints for different websites.</li>
                                    <li><strong>Business-Level:</strong> Set a default webhook URL in your business settings that will be used as a fallback.</li>
                                </ol>
                                <p class="text-gray-700 mb-4">
                                    <strong>Note:</strong> Webhook URLs must be from your approved website domains. Add and approve websites in your dashboard before using them.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Code Examples -->
                    <div id="code-examples" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-6">
                            <i class="fas fa-code text-primary mr-2"></i>Code Examples
                        </h2>

                        <div class="space-y-8">
                            <!-- PHP Example -->
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">PHP</h3>
                                <div class="code-block">
                                    <pre><code>$apiKey = 'pk_your_api_key_here';
$apiUrl = '{{ url('/api/v1') }}';

$data = [
    'amount' => 5000.00,
    'payer_name' => 'John Doe', // Required
    'bank' => 'GTBank',
    'webhook_url' => 'https://yourwebsite.com/webhook/payment-status',
    'service' => 'Product Purchase'
];

$ch = curl_init($apiUrl . '/payment-request');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-Key: ' . $apiKey
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 201 && $result['success']) {
    echo "Payment created: " . $result['data']['transaction_id'];
    echo "Account Number: " . $result['data']['account_number'];
} else {
    echo "Error: " . $result['message'];
}</code></pre>
                                </div>
                            </div>

                            <!-- JavaScript Example -->
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">JavaScript (Fetch API)</h3>
                                <div class="code-block">
                                    <pre><code>const apiKey = 'pk_your_api_key_here';
const apiUrl = '{{ url('/api/v1') }}';

const createPayment = async () => {
  const response = await fetch(`${apiUrl}/payment-request`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-API-Key': apiKey
    },
    body: JSON.stringify({
      amount: 5000.00,
      payer_name: 'John Doe',
      bank: 'GTBank',
      webhook_url: 'https://yourwebsite.com/webhook/payment-status',
      service: 'Product Purchase'
    })
  });

  const result = await response.json();

  if (result.success) {
    console.log('Payment created:', result.data.transaction_id);
    console.log('Account Number:', result.data.account_number);
  } else {
    console.error('Error:', result.message);
  }
};

createPayment();</code></pre>
                                </div>
                            </div>

                            <!-- Python Example -->
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">Python</h3>
                                <div class="code-block">
                                    <pre><code>import requests
import json

api_key = 'pk_your_api_key_here'
api_url = '{{ url('/api/v1') }}'

data = {
    'amount': 5000.00,
    'payer_name': 'John Doe',  # Required
    'bank': 'GTBank',
    'webhook_url': 'https://yourwebsite.com/webhook/payment-status',
    'service': 'Product Purchase'
}

headers = {
    'Content-Type': 'application/json',
    'X-API-Key': api_key
}

response = requests.post(
    f'{api_url}/payment-request',
    headers=headers,
    data=json.dumps(data)
)

result = response.json()

if response.status_code == 201 and result['success']:
    print(f"Payment created: {result['data']['transaction_id']}")
    print(f"Account Number: {result['data']['account_number']}")
else:
    print(f"Error: {result['message']}")</code></pre>
                                </div>
                            </div>

                            <!-- Webhook Handler Example -->
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">Webhook Handler (PHP)</h3>
                                <div class="code-block">
                                    <pre><code>&lt;?php
// webhook-handler.php

$payload = json_decode(file_get_contents('php://input'), true);

if ($payload['event'] === 'payment.approved') {
    $transactionId = $payload['transaction_id'];
    $amount = $payload['amount'];
    $status = $payload['status'];
    
    // Update your database
    // Mark order as paid, send confirmation email, etc.
    
    // Always return 200 OK to acknowledge receipt
    http_response_code(200);
    echo json_encode(['status' => 'received']);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown event']);
}
?&gt;</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Error Handling -->
                    <div id="error-handling" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-exclamation-triangle text-primary mr-2"></i>Error Handling
                        </h2>

                        <div class="space-y-6">
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">Error Response Format</h3>
                                <p class="text-gray-700 mb-4">All errors follow a consistent format:</p>
                                <div class="code-block">
                                    <pre><code>{
  "success": false,
  "message": "Error description here"
}</code></pre>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">HTTP Status Codes</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-gray-700 font-semibold">Code</th>
                                                <th class="px-4 py-3 text-left text-gray-700 font-semibold">Meaning</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <tr>
                                                <td class="px-4 py-3 font-mono text-gray-900">200</td>
                                                <td class="px-4 py-3 text-gray-700">Success</td>
                                            </tr>
                                            <tr>
                                                <td class="px-4 py-3 font-mono text-gray-900">201</td>
                                                <td class="px-4 py-3 text-gray-700">Created (payment request created)</td>
                                            </tr>
                                            <tr>
                                                <td class="px-4 py-3 font-mono text-gray-900">400</td>
                                                <td class="px-4 py-3 text-gray-700">Bad Request (validation error)</td>
                                            </tr>
                                            <tr>
                                                <td class="px-4 py-3 font-mono text-gray-900">401</td>
                                                <td class="px-4 py-3 text-gray-700">Unauthorized (invalid or missing API key)</td>
                                            </tr>
                                            <tr>
                                                <td class="px-4 py-3 font-mono text-gray-900">404</td>
                                                <td class="px-4 py-3 text-gray-700">Not Found</td>
                                            </tr>
                                            <tr>
                                                <td class="px-4 py-3 font-mono text-gray-900">500</td>
                                                <td class="px-4 py-3 text-gray-700">Internal Server Error</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">Common Errors</h3>
                                <div class="space-y-3">
                                    <div class="border-l-4 border-red-500 pl-4">
                                        <h4 class="font-semibold text-gray-900">Invalid API Key</h4>
                                        <p class="text-gray-700 text-sm">Status: 401</p>
                                        <div class="code-block mt-2">
                                            <pre><code>{
  "success": false,
  "message": "Invalid API key"
}</code></pre>
                                        </div>
                                    </div>
                                    <div class="border-l-4 border-red-500 pl-4">
                                        <h4 class="font-semibold text-gray-900">Missing Payer Name</h4>
                                        <p class="text-gray-700 text-sm">Status: 400</p>
                                        <div class="code-block mt-2">
                                            <pre><code>{
  "success": false,
  "message": "The payer name field is required. Please provide either \"name\" or \"payer_name\"."
}</code></pre>
                                        </div>
                                    </div>
                                    <div class="border-l-4 border-red-500 pl-4">
                                        <h4 class="font-semibold text-gray-900">Webhook URL Not Approved</h4>
                                        <p class="text-gray-700 text-sm">Status: 400</p>
                                        <div class="code-block mt-2">
                                            <pre><code>{
  "success": false,
  "message": "Webhook URL must be from your approved website domain."
}</code></pre>
                                        </div>
                                    </div>
                                    <div class="border-l-4 border-red-500 pl-4">
                                        <h4 class="font-semibold text-gray-900">Insufficient Balance</h4>
                                        <p class="text-gray-700 text-sm">Status: 400</p>
                                        <div class="code-block mt-2">
                                            <pre><code>{
  "success": false,
  "message": "Insufficient balance",
  "available_balance": 5000.00
}</code></pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rate Limits -->
                    <div id="rate-limits" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-tachometer-alt text-primary mr-2"></i>Rate Limits
                        </h2>
                        <div class="space-y-4">
                            <p class="text-gray-700">
                                API rate limits are applied per API key. Current limits:
                            </p>
                            <ul class="list-disc list-inside text-gray-700 space-y-2">
                                <li><strong>100 requests per minute</strong> per API key</li>
                                <li><strong>1000 requests per hour</strong> per API key</li>
                            </ul>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <p class="text-sm text-blue-800">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    If you exceed rate limits, you'll receive a <code class="bg-blue-100 px-1 py-0.5 rounded">429 Too Many Requests</code> response. Implement exponential backoff for retries.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Support Section -->
                    <div class="bg-gradient-to-r from-primary to-primary/90 rounded-lg shadow-sm p-6 sm:p-8 text-white">
                        <h2 class="text-2xl font-bold mb-4">
                            <i class="fas fa-life-ring mr-2"></i>Need Help?
                        </h2>
                        <p class="mb-4">Get support from our team or check out additional resources.</p>
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('support.index') }}" class="px-4 py-2 bg-white text-primary rounded-lg hover:bg-gray-100 font-medium">
                                <i class="fas fa-headset mr-2"></i> Contact Support
                            </a>
                            <a href="{{ route('faqs.index') }}" class="px-4 py-2 bg-white/10 backdrop-blur-sm text-white border border-white/20 rounded-lg hover:bg-white/20 font-medium">
                                <i class="fas fa-question-circle mr-2"></i> FAQs
                            </a>
                            <a href="{{ route('business.register') }}" class="px-4 py-2 bg-white text-primary rounded-lg hover:bg-gray-100 font-medium">
                                <i class="fas fa-rocket mr-2"></i> Get Started
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @include('partials.footer')

    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Copy code blocks
        document.querySelectorAll('.code-block').forEach(block => {
            block.addEventListener('click', function() {
                const code = this.querySelector('code').textContent;
                navigator.clipboard.writeText(code).then(() => {
                    const originalBg = this.style.backgroundColor;
                    this.style.backgroundColor = '#10b981';
                    setTimeout(() => {
                        this.style.backgroundColor = originalBg;
                    }, 200);
                });
            });
        });
    </script>
</body>
</html>

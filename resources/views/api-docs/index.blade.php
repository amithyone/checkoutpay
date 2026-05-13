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
        .badge-patch { background: #f59e0b; color: white; }
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
                            <a href="#developer-program" class="block text-sm text-gray-700 hover:text-primary py-2 ml-4">Developer program</a>
                            <a href="#update-amount" class="block text-sm text-gray-700 hover:text-primary py-2 ml-4">Update payment amount</a>
                            <a href="#whatsapp-wallet" class="block text-sm text-gray-700 hover:text-primary py-2">WhatsApp wallet API</a>
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
                                    <li>Include <code class="bg-gray-100 px-2 py-1 rounded">X-API-Key</code> header for authenticated endpoints (or send <code class="bg-gray-100 px-2 py-1 rounded">api_key</code> in the JSON body for POST/PATCH only—prefer the header in production)</li>
                                    <li>Send JSON data in request body (for POST/PUT requests)</li>
                                    <li>Use <code class="bg-gray-100 px-2 py-1 rounded">Content-Type: application/json</code> header</li>
                                </ul>
                                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                    <p class="text-sm text-amber-900 mb-2">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        <strong>Use the correct HTTP method.</strong> For example, <code class="bg-amber-100 px-1 rounded">POST /api/v1/payment-request</code> creates a payment. Opening that URL in a browser sends <strong>GET</strong>, which returns <strong>405 Method Not Allowed</strong> (only POST is supported). Call the API from your server, Postman, or curl with <code class="bg-amber-100 px-1 rounded">POST</code>.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Authentication -->
                    <div id="authentication" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-key text-primary mr-2"></i>Authentication
                        </h2>
                        
                        <p class="text-gray-700 mb-4">Authenticated routes accept your API key in the <code class="bg-gray-100 px-2 py-1 rounded">X-API-Key</code> header (recommended), or as <code class="bg-gray-100 px-2 py-1 rounded">api_key</code> in the JSON body for POST/PATCH requests.</p>
                        
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
                                <p class="text-gray-700 mb-4">Create a new payment request. Returns account details for the customer to make payment. <strong>POST only</strong>—do not use GET (e.g. pasting the URL into a browser).</p>
                                <p class="text-gray-600 text-sm mb-4">Integrations such as the <strong>CheckoutPay WordPress plugin</strong> should send the same optional JSON key (<code class="bg-gray-100 px-1 rounded text-xs">developer_program_partner_business_id</code> or alias <code class="bg-gray-100 px-1 rounded text-xs">devprogram</code>) on this request when partner attribution is configured; omit the field when not used.</p>

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
  "website_url": "https://yourwebsite.com",
  "developer_program_partner_business_id": 42
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
                                                    <td class="px-4 py-3 font-mono text-gray-900">fname</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">No</td>
                                                    <td class="px-4 py-3 text-gray-700">Customer first name</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">lname</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">No</td>
                                                    <td class="px-4 py-3 text-gray-700">Customer last name</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">bvn</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">No</td>
                                                    <td class="px-4 py-3 text-gray-700">BVN (if collected)</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">registration_number</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">No</td>
                                                    <td class="px-4 py-3 text-gray-700">Registration number (if applicable)</td>
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
                                                    <td class="px-4 py-3 text-gray-700">Your unique transaction ID if provided; must not duplicate an existing payment</td>
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
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">developer_program_partner_business_id</td>
                                                    <td class="px-4 py-3 text-gray-700">integer</td>
                                                    <td class="px-4 py-3 text-gray-700">No</td>
                                                    <td class="px-4 py-3 text-gray-700">Optional. CheckoutPay <strong>Business ID</strong> of the approved developer program partner to attribute this payment to (not your merchant ID from the API key). When omitted or null, behavior is unchanged. Must reference a business with an <strong>approved</strong> developer program application and cannot be the same business as the merchant.</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">devprogram</td>
                                                    <td class="px-4 py-3 text-gray-700">integer</td>
                                                    <td class="px-4 py-3 text-gray-700">No</td>
                                                    <td class="px-4 py-3 text-gray-700">Alias for <code class="bg-gray-100 px-1 rounded">developer_program_partner_business_id</code> when the long key is not sent.</td>
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

                            <!-- Update payment amount (correct wrong amount) -->
                            <div id="update-amount" class="mb-6 border-l-4 border-amber-500 pl-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="endpoint-badge badge-patch">PATCH</span>
                                    <code class="text-lg font-mono text-gray-900">/payment/{transactionId}/amount</code>
                                </div>
                                <p class="text-gray-700 mb-4">Correct the expected amount for a <strong>pending</strong> payment. Use this when your site sent the wrong amount (e.g. customer paid a different sum). The system updates the transaction amount, recalculates charges, and immediately re-runs email matching so any bank alert with the actual amount paid can be matched and the payment approved.</p>
                                <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                    <p class="text-sm text-amber-800"><strong>When to use:</strong> Only pending, non-expired payments can be updated.</p>
                                    <p class="text-sm text-amber-800 mt-1"><strong>Recommended flow:</strong> Call <code class="bg-amber-100 px-1 rounded">PATCH /payment/{transactionId}/amount</code> with the correct amount, then poll <code class="bg-amber-100 px-1 rounded">GET /payment/{transactionId}</code> until status changes, or rely on your webhook for final confirmation. The webhook sent when a payment is approved is <strong>unchanged</strong> (same <code>payment.approved</code> payload) when the payment was matched after an amount correction.</p>
                                </div>
                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-900 mb-2">Request Body</h4>
                                    <div class="code-block">
                                        <pre><code>{
  "new_amount": 7500.00
}</code></pre>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-900 mb-2">Response (200 OK)</h4>
                                    <div class="code-block">
                                        <pre><code>{
  "success": true,
  "message": "Transaction amount successfully updated. Recalculated charges and matching initiated.",
  "data": {
    "transaction_id": "TXN-1234567890",
    "amount": 7500.00,
    "payer_name": "John Doe",
    "bank": "GTBank",
    "account_number": "0123456789",
    "account_name": "Your Business Name",
    "bank_name": "GTBank",
    "status": "pending",
    "webhook_url": "https://yourwebsite.com/webhook/payment-status",
    "expires_at": "2024-01-15T12:00:00Z",
    "matched_at": null,
    "approved_at": null,
    "created_at": "2024-01-15T10:00:00Z",
    "updated_at": "2024-01-15T10:15:00Z",
    "charges": { "percentage": 50.00, "fixed": 50.00, "total": 100.00, "paid_by_customer": false, "business_receives": 7400.00 },
    "website": { "id": 1, "url": "https://yourwebsite.com" }
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
                                <p class="text-gray-700 mb-4">Retrieve payment details by transaction ID. Use this to poll for status after creating a payment or after correcting the amount with PATCH. Response structure is stable; new fields may be added.</p>

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
                    </div>

                    <!-- WhatsApp wallet merchant API -->
                    <div id="whatsapp-wallet" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">
                            <i class="fab fa-whatsapp text-green-600 mr-2"></i>WhatsApp wallet (merchant API)
                        </h2>
                        <p class="text-gray-700 mb-4">
                            Lets your server use the same <code class="bg-gray-100 px-2 py-1 rounded text-sm">X-API-Key</code> as bank-transfer payments. Checkout must <strong>enable WhatsApp wallet API</strong> on your business (admin). Nigerian wallet numbers only. Throttle: <strong>30 requests/minute</strong> on this group (in addition to global API limits).
                        </p>

                        <div class="space-y-8">
                            <div class="border-l-4 border-green-500 pl-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="endpoint-badge badge-post">POST</span>
                                    <code class="text-lg font-mono text-gray-900">/whatsapp-wallet/lookup</code>
                                </div>
                                <p class="text-gray-700 mb-3">JSON body: <code class="bg-gray-100 px-1 rounded">phone</code>. Returns <code class="bg-gray-100 px-1 rounded">balance</code>, <code class="bg-gray-100 px-1 rounded">wallet_id</code>, <code class="bg-gray-100 px-1 rounded">has_pin</code>, <code class="bg-gray-100 px-1 rounded">tier</code>.</p>
                            </div>

                            <div class="border-l-4 border-green-500 pl-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="endpoint-badge badge-post">POST</span>
                                    <code class="text-lg font-mono text-gray-900">/whatsapp-wallet/ensure</code>
                                </div>
                                <p class="text-gray-700 mb-3">JSON body: <code class="bg-gray-100 px-1 rounded">phone</code>. Creates a Tier-1 wallet row if missing.</p>
                            </div>

                            <div class="border-l-4 border-green-500 pl-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="endpoint-badge badge-post">POST</span>
                                    <code class="text-lg font-mono text-gray-900">/whatsapp-wallet/send-message</code>
                                </div>
                                <p class="text-gray-700 mb-3">JSON body: <code class="bg-gray-100 px-1 rounded">phone</code>, <code class="bg-gray-100 px-1 rounded">message</code> (max 4000). Sends your composed plain text via WhatsApp (e.g. OTP). Same <code class="bg-gray-100 px-1 rounded">X-API-Key</code> as other wallet routes — no extra Checkout env secret per merchant.</p>
                            </div>

                            <div class="border-l-4 border-green-600 pl-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="endpoint-badge badge-post">POST</span>
                                    <code class="text-lg font-mono text-gray-900">/whatsapp-wallet/pay/start</code>
                                </div>
                                <p class="text-gray-700 mb-3"><strong>Recommended:</strong> Your backend sends <code class="bg-gray-100 px-1 rounded">order_summary</code> and amount; Checkout sends the customer a WhatsApp with a <strong>secure PIN link</strong>. On success, credits your business and POSTs <code class="bg-gray-100 px-1 rounded">payment.approved</code> to <code class="bg-gray-100 px-1 rounded">webhook_url</code> (must match your saved business or approved website webhook URL).</p>
                                <div class="code-block mb-3">
                                    <pre><code>POST {{ url('/api/v1/whatsapp-wallet/pay/start') }}
Content-Type: application/json
X-API-Key: pk_your_api_key_here

{
  "phone": "08012345678",
  "amount": 2500.00,
  "order_reference": "ORDER-TRACK-123",
  "order_summary": "2x Jollof rice\n1x Zobo\nDelivery: Surulere",
  "payer_name": "Ada Customer",
  "webhook_url": "https://your-app.com/api/webhooks/checkout/payment",
  "idempotency_key": "order-123-wallet-try-1"
}</code></pre>
                                </div>
                                <p class="text-sm text-gray-600">Response <code class="bg-gray-100 px-1 rounded">data.confirm_url</code> is the same URL messaged to the customer. Link TTL from env <code class="bg-gray-100 px-1 rounded">WHATSAPP_WALLET_PARTNER_PAY_INTENT_TTL_MINUTES</code> (default 30).</p>
                            </div>

                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                <p class="text-sm text-amber-900"><strong>No PIN-less debit endpoint.</strong> Merchant debits always require the customer to confirm on the Checkout PIN page after <strong>pay/start</strong> (WhatsApp link).</p>
                            </div>
                        </div>
                    </div>

                    <!-- Developer program -->
                    <div id="developer-program" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-handshake text-primary mr-2"></i>Developer program (partner attribution)
                        </h2>
                        <p class="text-gray-700 mb-4">CheckoutPay can attribute a payment to an <strong>approved developer partner</strong> (another business on the platform) when you create the payment via the standard API. The partner&rsquo;s <strong>Business ID</strong> is the numeric primary key (<code>businesses.id</code>) shown in the dashboard—not your merchant ID from the API key.</p>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Create payment (<code>POST /api/v1/payment-request</code>)</h3>
                        <p class="text-gray-700 mb-3">Optional body fields (see <a href="#payments" class="text-primary underline">Payments</a> for full parameter list):</p>
                        <ul class="list-disc list-inside text-gray-700 space-y-1 mb-4">
                            <li><code class="bg-gray-100 px-1 rounded">developer_program_partner_business_id</code> (integer)—partner developer&rsquo;s Business ID.</li>
                            <li><code class="bg-gray-100 px-1 rounded">devprogram</code> (integer)—alias for the same value when the long key is omitted.</li>
                        </ul>
                        <p class="text-gray-700 mb-4">Validation: partner must exist, must have an <strong>approved</strong> developer program application for that Business ID, and must <strong>not</strong> be the same business as the merchant authenticated by <code>X-API-Key</code>. Omitting the field leaves behavior unchanged.</p>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Partner fee share (on approval)</h3>
                        <p class="text-gray-700 mb-4">When a attributed payment is <strong>approved</strong>, the platform may credit the partner&rsquo;s <strong>business balance</strong> with a percentage of <strong>platform transaction fees</strong> on that payment (<code>charges.total</code> / <code>total_charges</code> in the webhook—i.e. CheckoutPay&rsquo;s fee revenue, not the merchant&rsquo;s <code>business_receives</code>). The percentage comes from the admin developer program defaults and/or the partner&rsquo;s approved application override. If fees are zero (e.g. some exempt flows), the credited share is zero.</p>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2"><code>payment.approved</code> webhook (extra fields)</h3>
                        <p class="text-gray-700 mb-3">In addition to the standard payload (see <a href="#webhooks" class="text-primary underline">Webhooks</a>), these nullable fields may appear:</p>
                        <ul class="list-disc list-inside text-gray-700 space-y-1 mb-4">
                            <li><code class="bg-gray-100 px-1 rounded">developer_program_partner_business_id</code>—set when the payment was attributed at creation.</li>
                            <li><code class="bg-gray-100 px-1 rounded">developer_program_partner_share_amount</code>—amount credited to the partner&rsquo;s balance when a share applies; otherwise <code>null</code>.</li>
                            <li><code class="bg-gray-100 px-1 rounded">developer_program_partner_share_percent_effective</code>—effective percentage implied by the credited amount vs. <code>charges.total</code>, when both are positive; otherwise <code>null</code>.</li>
                            <li><code class="bg-gray-100 px-1 rounded">developer_program_fee_share_base_description</code>—short admin-configured phrase describing what the published percentage applies to (may be <code>null</code>).</li>
                        </ul>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Where partner attribution is supported</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm border border-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-700">Flow</th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-700">Partner fields on create</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 text-gray-700">
                                    <tr><td class="px-4 py-2">Standard REST <code>POST /api/v1/payment-request</code></td><td class="px-4 py-2">Yes (optional)</td></tr>
                                    <tr><td class="px-4 py-2">Hosted checkout, invoice pay links, tickets, membership, rentals, other internal flows</td><td class="px-4 py-2">Not exposed—use the standard API for attribution</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-sm text-gray-600 mt-4"><strong>WordPress / WooCommerce:</strong> configure the partner Business ID in plugin settings and send <code>developer_program_partner_business_id</code> (or <code>devprogram</code>) on <code>POST .../payment-request</code> only.</p>
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
                                <p class="text-gray-700 mb-4">When a payment is approved, you'll receive a POST request to your webhook URL with the following payload (structure is stable; new fields may be added in the future). This includes payments that were matched after an amount correction via <code>PATCH /payment/{transactionId}/amount</code>—the webhook payload is unchanged.</p>
                                <div class="code-block">
                                    <pre><code>{
  "event": "payment.approved",
  "transaction_id": "TXN-1234567890",
  "external_reference": "ORDER-TRACK-123",
  "status": "approved",
  "amount": 5000.00,
  "received_amount": 5000.00,
  "payer_name": "John Doe",
  "bank": "GTBank",
  "payer_account_number": "0123456789",
  "account_number": "0987654321",
  "is_mismatch": false,
  "mismatch_reason": null,
  "charges": {
    "percentage": 50.00,
    "fixed": 50.00,
    "total": 100.00,
    "business_receives": 4900.00
  },
  "timestamp": "2024-01-15T10:35:00Z",
  "email_data": {},
  "developer_program_partner_business_id": 42,
  "developer_program_partner_share_amount": 25.00,
  "developer_program_partner_share_percent_effective": 25,
  "developer_program_fee_share_base_description": "CheckoutPay's transaction fee revenue on qualifying attributed volume"
}</code></pre>
                                </div>
                                <p class="text-sm text-gray-600 mt-2"><strong>Fields:</strong> <code>event</code>, <code>transaction_id</code>, <code>external_reference</code> (when set on the payment, e.g. WhatsApp wallet <code>pay/start</code> <code>order_reference</code>), <code>status</code>, <code>amount</code> (requested), <code>received_amount</code> (actual received; use for reconciliation), <code>payer_name</code>, <code>bank</code>, <code>payer_account_number</code>, <code>account_number</code> (your account), <code>is_mismatch</code>, <code>mismatch_reason</code>, <code>charges</code>, <code>timestamp</code>, <code>email_data</code> (optional raw email info). Developer program (nullable): <code>developer_program_partner_business_id</code>, <code>developer_program_partner_share_amount</code>, <code>developer_program_partner_share_percent_effective</code>, <code>developer_program_fee_share_base_description</code>—see <a href="#developer-program" class="text-primary underline">Developer program</a>.</p>
                            </div>

                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">Charges Mismatch Handling</h3>
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                                    <p class="text-sm text-blue-800 mb-3">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        <strong>Automatic Charges Mismatch Detection:</strong> Our system automatically detects when a customer pays the base amount without including charges.
                                    </p>
                                    <p class="text-sm text-blue-800 mb-3">
                                        If the following conditions are met, the payment will be automatically approved with a mismatch flag:
                                    </p>
                                    <ul class="list-disc list-inside text-sm text-blue-800 space-y-1 ml-4 mb-3">
                                        <li>The payer name matches the expected name</li>
                                        <li>The received amount is less than the requested amount</li>
                                        <li>The difference equals the calculated charges (within ₦1 tolerance)</li>
                                    </ul>
                                    <p class="text-sm text-blue-800">
                                        In this case, the webhook will include:
                                    </p>
                                    <ul class="list-disc list-inside text-sm text-blue-800 space-y-1 ml-4">
                                        <li><code>is_mismatch: true</code></li>
                                        <li><code>received_amount</code> - The actual amount received (base amount without charges)</li>
                                        <li><code>mismatch_reason</code> - Explanation of the mismatch</li>
                                        <li><code>amount</code> - The originally requested amount (includes charges)</li>
                                    </ul>
                                </div>
                                <div class="code-block mb-4">
                                    <pre><code>{
  "event": "payment.approved",
  "transaction_id": "TXN-1234567890",
  "status": "approved",
  "amount": 2070.00,  // Requested amount (includes charges)
  "received_amount": 2000.00,  // Actual amount received (base amount)
  "is_mismatch": true,
  "mismatch_reason": "Customer paid base amount without charges. Expected: ₦2,070.00, Received: ₦2,000.00 (charges: ₦70.00)",
  "name_mismatch": false,
  "charges": {
    "percentage": 20.00,
    "fixed": 50.00,
    "total": 70.00,
    "paid_by_customer": false,
    "business_receives": 1930.00  // received_amount - charges
  },
  ...
}</code></pre>
                                </div>
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <p class="text-sm text-yellow-800">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        <strong>Important:</strong> When handling charges mismatch, your business balance will be credited with the <code>business_receives</code> amount (received_amount minus charges), not the full requested amount. Always check <code>is_mismatch</code> and <code>received_amount</code> fields in your webhook handler to process payments correctly.
                                    </p>
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
                                <p class="text-gray-700 mb-4">Application-level errors (API key, webhook domain, not found) usually return <code class="bg-gray-100 px-1 rounded">success: false</code> and a <code class="bg-gray-100 px-1 rounded">message</code> string. Laravel <strong>validation</strong> errors (HTTP 422) return <code class="bg-gray-100 px-1 rounded">message</code> (often the first problem found) and an <code class="bg-gray-100 px-1 rounded">errors</code> object keyed by field—inspect <code class="bg-gray-100 px-1 rounded">errors</code> in your client.</p>
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
                                                <td class="px-4 py-3 text-gray-700">Bad Request (e.g. webhook domain not approved)</td>
                                            </tr>
                                            <tr>
                                                <td class="px-4 py-3 font-mono text-gray-900">401</td>
                                                <td class="px-4 py-3 text-gray-700">Unauthorized (invalid, inactive, or missing API key)</td>
                                            </tr>
                                            <tr>
                                                <td class="px-4 py-3 font-mono text-gray-900">404</td>
                                                <td class="px-4 py-3 text-gray-700">Not Found</td>
                                            </tr>
                                            <tr>
                                                <td class="px-4 py-3 font-mono text-gray-900">405</td>
                                                <td class="px-4 py-3 text-gray-700">Method Not Allowed (wrong HTTP verb, e.g. GET on POST-only routes)</td>
                                            </tr>
                                            <tr>
                                                <td class="px-4 py-3 font-mono text-gray-900">422</td>
                                                <td class="px-4 py-3 text-gray-700">Unprocessable Entity (Laravel validation errors; response includes <code class="bg-gray-100 px-1 rounded">errors</code> by field)</td>
                                            </tr>
                                            <tr>
                                                <td class="px-4 py-3 font-mono text-gray-900">429</td>
                                                <td class="px-4 py-3 text-gray-700">Too Many Requests (rate limit exceeded)</td>
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
                                        <h4 class="font-semibold text-gray-900">Invalid or inactive API key</h4>
                                        <p class="text-gray-700 text-sm">Status: 401</p>
                                        <div class="code-block mt-2">
                                            <pre><code>{
  "success": false,
  "message": "Invalid or inactive API key"
}</code></pre>
                                        </div>
                                    </div>
                                    <div class="border-l-4 border-red-500 pl-4">
                                        <h4 class="font-semibold text-gray-900">Missing API key</h4>
                                        <p class="text-gray-700 text-sm">Status: 401</p>
                                        <div class="code-block mt-2">
                                            <pre><code>{
  "success": false,
  "message": "API key is required"
}</code></pre>
                                        </div>
                                    </div>
                                    <div class="border-l-4 border-red-500 pl-4">
                                        <h4 class="font-semibold text-gray-900">Missing payer name</h4>
                                        <p class="text-gray-700 text-sm">Status: 422 (validation)</p>
                                        <div class="code-block mt-2">
                                            <pre><code>{
  "message": "The payer name is required to get an account number. Please provide either \"name\" or \"payer_name\".",
  "errors": {
    "payer_name": [
      "The payer name is required to get an account number. Please provide either \"name\" or \"payer_name\"."
    ]
  }
}</code></pre>
                                        </div>
                                        <p class="text-gray-600 text-xs mt-2">Exact wording may vary slightly; always inspect the <code class="bg-gray-100 px-1 rounded">errors</code> object.</p>
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
                                The <code class="bg-gray-100 px-1 py-0.5 rounded">api</code> middleware applies Laravel’s default API rate limiter: <strong>60 requests per minute</strong>, keyed by authenticated user id when the request is authenticated, otherwise by IP address.
                            </p>
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

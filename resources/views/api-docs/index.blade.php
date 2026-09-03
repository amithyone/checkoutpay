@extends('layouts.marketing')

@section('title')
    @include('partials.marketing-head', [
        'seoPath' => '/api-docs',
        'jsonLdExtra' => [\App\Support\FaqCatalog::faqPageJsonLd(\App\Support\FaqCatalog::forCategory('api'))],
    ])
@endsection

@push('head')
<style>
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
@endpush

@section('content')

    <!-- Hero Section -->
    <section class="py-12 sm:py-16">
        <div class="max-w-container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <div class="badge-brand mx-auto mb-4"><i class="fas fa-code"></i> REST API</div>
                <h1 class="section-heading mb-4">
                    API Documentation
                </h1>
                <p class="section-subheading mx-auto">
                    Complete integration guide for CheckoutPay Payment Gateway API. Build powerful payment solutions with our RESTful API.
                </p>
                <p class="mt-6 text-sm text-slate-500 font-medium max-w-2xl mx-auto">
                    Building for clients?
                    <a href="{{ route('developers.program') }}" class="text-brand-primary font-semibold hover:underline">Developer Program (revenue share)</a>
                    ·
                    <a href="{{ route('wordpress-plugin.index') }}" class="text-brand-primary font-semibold hover:underline">WordPress plugin</a>
                    ·
                    <a href="{{ route('faqs.index') }}#api" class="text-brand-primary font-semibold hover:underline">API FAQs</a>
                </p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="py-12">
        <div class="max-w-container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <!-- Sidebar Navigation -->
                <div class="lg:col-span-1">
                    <div class="card-marketing p-4 sticky top-24">
                        <h3 class="font-bold text-midnight-deep mb-4">Quick Navigation</h3>
                        <nav class="space-y-2">
                            <a href="#getting-started" class="block text-sm text-slate-600 hover:text-brand-primary py-2 font-medium">Getting Started</a>
                            <a href="#authentication" class="block text-sm text-slate-600 hover:text-brand-primary py-2 font-medium">Authentication</a>
                            <a href="#endpoints" class="block text-sm text-slate-600 hover:text-brand-primary py-2 font-medium">API Endpoints</a>
                            <a href="#payments" class="block text-sm text-gray-700 hover:text-primary py-2 ml-4">Payments</a>
                            <a href="#card-payments" class="block text-sm text-gray-700 hover:text-primary py-2 ml-4">Card collection</a>
                            <a href="#whatsapp-pay-code" class="block text-sm text-gray-700 hover:text-primary py-2 ml-4">WhatsApp Pay Code</a>
                            <a href="#developer-program" class="block text-sm text-gray-700 hover:text-primary py-2 ml-4">Developer program</a>
                            <a href="#update-amount" class="block text-sm text-gray-700 hover:text-primary py-2 ml-4">Update payment amount</a>
                            <a href="#payouts" class="block text-sm text-gray-700 hover:text-primary py-2">Payouts</a>
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
                                <div class="code-block-light">
                                    <code class="text-sm">{{ url('/api/v1') }}</code>
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
                        
                        <div class="code-block-dark mb-4">
                            <pre><code>X-API-Key: pk_your_api_key_here</code></pre>
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
                                                    <td class="px-4 py-3 font-mono text-gray-900">payment_method</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">No</td>
                                                    <td class="px-4 py-3 text-gray-700"><code>bank_transfer</code> (default) or <code>card</code>. Omit for virtual-account bank transfer.</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">email</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">Yes if card</td>
                                                    <td class="px-4 py-3 text-gray-700">Customer email — required when <code>payment_method</code> is <code>card</code></td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">phone</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">No</td>
                                                    <td class="px-4 py-3 text-gray-700">Customer phone (optional for card checkout)</td>
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
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">include_whatsapp_pay</td>
                                                    <td class="px-4 py-3 text-gray-700">boolean</td>
                                                    <td class="px-4 py-3 text-gray-700">No</td>
                                                    <td class="px-4 py-3 text-gray-700">Default <code class="bg-gray-100 px-1 rounded">true</code>. When your business has WhatsApp wallet API enabled and Pay Code is allowed for at least one country, include a <code class="bg-gray-100 px-1 rounded">whatsapp_pay</code> block in the response. Set <code class="bg-gray-100 px-1 rounded">false</code> to omit it.</td>
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
    },
    "whatsapp_pay": {
      "code": "ABC12",
      "message": "PAY ABC12",
      "wa_link": "https://wa.me/234XXXXXXXXXX?text=PAY%20ABC12",
      "expires_at": "2024-01-15T10:30:00Z",
      "amount": 5000.00,
      "enabled_countries": ["NG"],
      "status": "available",
      "instructions": "Send the message on WhatsApp, then enter your wallet PIN on the secure link."
    }
  }
}</code></pre>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-2"><code class="bg-gray-100 px-1 rounded">whatsapp_pay</code> is omitted when WhatsApp wallet API is disabled on your business, Pay Code is disabled platform-wide, or you send <code class="bg-gray-100 px-1 rounded">include_whatsapp_pay: false</code>. Bank transfer fields are unchanged — existing integrations can ignore the new block.</p>
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

                    <!-- Card payments (optional) -->
                    <div id="card-payments" class="bg-white rounded-lg shadow-sm border border-indigo-200 p-6 sm:p-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-credit-card text-indigo-600 mr-2"></i>Card payments (optional)
                        </h2>
                        <p class="text-gray-700 mb-4">
                            Opt-in rail on the same <code class="bg-gray-100 px-2 py-1 rounded text-sm">POST /payment-request</code> endpoint.
                            Send <code class="bg-gray-100 px-1 rounded">payment_method: card</code> with a customer <code class="bg-gray-100 px-1 rounded">email</code> to receive a hosted checkout URL.
                            <strong>Bank transfer (virtual account) remains the default</strong> when you omit <code class="bg-gray-100 px-1 rounded">payment_method</code> or send <code class="bg-gray-100 px-1 rounded">bank_transfer</code>.
                        </p>

                        <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-6 text-sm text-indigo-900 space-y-2">
                            <p><strong>Enablement:</strong> Turn on <strong>Card payments</strong> in your CheckoutPay business dashboard under <strong>Settings → Card payments</strong>. Until then, card requests return <strong>403</strong>.</p>
                            <p><strong>Flow:</strong> Create → redirect customer to <code class="bg-white px-1 rounded">card_checkout.checkout_url</code> → customer pays → you receive the same <code class="bg-white px-1 rounded">payment.approved</code> webhook with <code class="bg-white px-1 rounded">payment_method: card</code>.</p>
                        </div>

                        <h3 class="text-xl font-semibold text-gray-900 mb-3">Example request</h3>
                        <div class="code-block mb-6">
                            <pre><code>{
  "amount": 200.00,
  "payment_method": "card",
  "email": "customer@example.com",
  "phone": "08012345678",
  "payer_name": "Jane Doe",
  "webhook_url": "https://yourwebsite.com/webhook/payment-status"
}</code></pre>
                        </div>

                        <h3 class="text-xl font-semibold text-gray-900 mb-3">Example response</h3>
                        <div class="code-block mb-6">
                            <pre><code>{
  "success": true,
  "message": "Payment request created successfully",
  "data": {
    "transaction_id": "TXN-ABC123",
    "amount": 200,
    "payment_method": "card",
    "status": "pending",
    "card_checkout": {
      "checkout_url": "https://checkout.example.com/pay/...",
      "payment_reference": "PAY_..."
    },
    "charges": { }
  }
}</code></pre>
                        </div>

                        <p class="text-gray-700 text-sm mb-6">There is no <code class="bg-gray-100 px-1 rounded">account_number</code> on card payments. Poll <code class="bg-gray-100 px-1 rounded">GET /payment/{transactionId}</code> or wait for <code class="bg-gray-100 px-1 rounded">payment.approved</code>. Use <code class="bg-gray-100 px-1 rounded">received_amount</code> for reconciliation (net after processor fees).</p>

                        <h3 class="text-xl font-semibold text-gray-900 mb-3">Sample: collect a card payment</h3>
                        <p class="text-gray-700 text-sm mb-4">Create the payment on your server, then redirect the customer to <code class="bg-gray-100 px-1 rounded">card_checkout.checkout_url</code>. Do not call the card processor yourself. When the customer finishes, you receive the same <code class="bg-gray-100 px-1 rounded">payment.approved</code> webhook with <code class="bg-gray-100 px-1 rounded">payment_method: card</code>.</p>

                        <h4 class="font-semibold text-gray-900 mb-2">PHP</h4>
                        <div class="code-block mb-6">
                            <pre><code>$apiKey = 'pk_your_api_key_here';
$apiUrl = '{{ url('/api/v1') }}';

$data = [
    'amount' => 200.00,
    'payment_method' => 'card',
    'email' => 'customer@example.com',
    'phone' => '08012345678',
    'payer_name' => 'Jane Doe',
    'webhook_url' => 'https://yourwebsite.com/webhook/payment-status',
    'service' => 'Order #1234',
];

$ch = curl_init($apiUrl . '/payment-request');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-Key: ' . $apiKey,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 201 && !empty($result['success'])) {
    $checkoutUrl = $result['data']['card_checkout']['checkout_url'] ?? null;
    if ($checkoutUrl) {
        header('Location: ' . $checkoutUrl);
        exit;
    }
}

http_response_code(502);
echo $result['message'] ?? 'Unable to start card checkout';</code></pre>
                        </div>

                        <h4 class="font-semibold text-gray-900 mb-2">JavaScript (Node / Fetch)</h4>
                        <div class="code-block mb-6">
                            <pre><code>const apiKey = 'pk_your_api_key_here';
const apiUrl = '{{ url('/api/v1') }}';

const response = await fetch(`${apiUrl}/payment-request`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-API-Key': apiKey,
  },
  body: JSON.stringify({
    amount: 200.00,
    payment_method: 'card',
    email: 'customer@example.com',
    phone: '08012345678',
    payer_name: 'Jane Doe',
    webhook_url: 'https://yourwebsite.com/webhook/payment-status',
    service: 'Order #1234',
  }),
});

const result = await response.json();

if (result.success &amp;&amp; result.data?.card_checkout?.checkout_url) {
  const checkoutUrl = result.data.card_checkout.checkout_url;
  // Call this from your backend, then send checkoutUrl to the browser and redirect
  window.location.href = checkoutUrl;
} else {
  throw new Error(result.message || 'Unable to start card checkout');
}</code></pre>
                        </div>

                        <h4 class="font-semibold text-gray-900 mb-2">Python</h4>
                        <div class="code-block mb-6">
                            <pre><code>import json
import requests

api_key = 'pk_your_api_key_here'
api_url = '{{ url('/api/v1') }}'

payload = {
    'amount': 200.00,
    'payment_method': 'card',
    'email': 'customer@example.com',
    'phone': '08012345678',
    'payer_name': 'Jane Doe',
    'webhook_url': 'https://yourwebsite.com/webhook/payment-status',
    'service': 'Order #1234',
}

response = requests.post(
    f'{api_url}/payment-request',
    headers={
        'Content-Type': 'application/json',
        'X-API-Key': api_key,
    },
    data=json.dumps(payload),
)
result = response.json()

if response.status_code == 201 and result.get('success'):
    checkout_url = result['data']['card_checkout']['checkout_url']
    # Redirect the customer to checkout_url
    print(checkout_url)
else:
    raise RuntimeError(result.get('message', 'Unable to start card checkout'))</code></pre>
                        </div>

                        <h4 class="font-semibold text-gray-900 mb-2">Webhook after a successful card payment</h4>
                        <p class="text-gray-700 text-sm mb-3"><code class="bg-gray-100 px-1 rounded">event</code> stays <code class="bg-gray-100 px-1 rounded">payment.approved</code>. Bank fields are <code class="bg-gray-100 px-1 rounded">null</code>. <code class="bg-gray-100 px-1 rounded">external_reference</code> is the card checkout payment reference when present.</p>
                        <div class="code-block">
                            <pre><code>{
  "event": "payment.approved",
  "transaction_id": "TXN-ABC123",
  "external_reference": "PAY_...",
  "status": "approved",
  "amount": 200.00,
  "received_amount": 196.00,
  "payer_name": "Jane Doe",
  "payerName": "Jane Doe",
  "sender_name": "Jane Doe",
  "bank": null,
  "bank_name": null,
  "payer_bank": null,
  "payer_account": null,
  "payer_account_number": null,
  "sender_account": null,
  "payer": {
    "name": "Jane Doe",
    "account": null,
    "bank": null
  },
  "account_number": null,
  "is_mismatch": false,
  "mismatch_reason": null,
  "charges": {
    "percentage": 1.5,
    "fixed": 50.00,
    "total": 4.00,
    "business_receives": 192.00
  },
  "timestamp": "2026-09-03T10:35:00Z",
  "payment_method": "card",
  "email_data": {}
}</code></pre>
                        </div>
                    </div>

                    <!-- WhatsApp Pay Code (checkout dual rail) -->
                    <div id="whatsapp-pay-code" class="bg-white rounded-lg shadow-sm border border-green-200 p-6 sm:p-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">
                            <i class="fab fa-whatsapp text-green-600 mr-2"></i>WhatsApp Pay Code
                        </h2>
                        <p class="text-gray-700 mb-4">
                            Optional second payment rail on <code class="bg-gray-100 px-2 py-1 rounded text-sm">POST /payment-request</code>. The customer pays the <strong>same</strong> pending payment by sending <code class="bg-gray-100 px-1 rounded">PAY {CODE}</code> on WhatsApp (or tapping the prefilled <code class="bg-gray-100 px-1 rounded">wa_link</code>), then entering their wallet PIN on a secure Checkout page. <strong>First successful method wins</strong> — bank transfer or WhatsApp — same <code class="bg-gray-100 px-1 rounded">transaction_id</code> and <code class="bg-gray-100 px-1 rounded">payment.approved</code> webhook.
                        </p>

                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 text-sm text-green-900 space-y-2">
                            <p><strong>Requirements:</strong> Admin enables Pay Code for customer countries (default Nigeria). Your business must have <strong>WhatsApp wallet API</strong> enabled.</p>
                            <p><strong>vs <code>pay/start</code>:</strong> Pay Code is <em>customer-initiated</em> at checkout (you do not need their phone). <code>POST /whatsapp-wallet/pay/start</code> is when you already know the customer number and push a WhatsApp message from your backend.</p>
                            <p><strong>UI tip:</strong> Show your virtual account <em>and</em> a “Pay on WhatsApp” button using <code class="bg-white px-1 rounded">whatsapp_pay.wa_link</code> or a QR code.</p>
                        </div>

                        <h3 class="text-xl font-semibold text-gray-900 mb-3">Poll payment status</h3>
                        <p class="text-gray-700 mb-3"><code class="bg-gray-100 px-1 rounded">GET /payment/{transactionId}</code> includes <code class="bg-gray-100 px-1 rounded">payment_method_used</code> (<code class="bg-gray-100 px-1 rounded">bank_transfer</code> | <code class="bg-gray-100 px-1 rounded">whatsapp_wallet</code> | <code class="bg-gray-100 px-1 rounded">card</code> | null while pending) and <code class="bg-gray-100 px-1 rounded">whatsapp_pay.status</code> (<code class="bg-gray-100 px-1 rounded">available</code>, <code class="bg-gray-100 px-1 rounded">claimed</code>, <code class="bg-gray-100 px-1 rounded">completed</code>, <code class="bg-gray-100 px-1 rounded">expired</code>).</p>

                        <h3 class="text-xl font-semibold text-gray-900 mb-3 mt-6">Security</h3>
                        <p class="text-gray-700">Wallet PIN is <strong>never</strong> accepted in WhatsApp chat. The bot sends a time-limited HTTPS link; the customer enters their 4-digit PIN only on that page.</p>
                    </div>

                    <!-- Payouts -->
                    <div id="payouts" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-university text-emerald-600 mr-2"></i>Payouts
                        </h2>
                        <p class="text-gray-700 mb-4">
                            Send money from your Checkout business balance to a Nigerian bank account. Same rail as <strong>Dashboard → Withdrawals</strong>.
                            Checkout must <strong>enable Payout API</strong> on your business (admin). Authenticate with <code class="bg-gray-100 px-2 py-1 rounded text-sm">X-API-Key</code>.
                            Sender on the bank statement follows <strong>Dashboard → Settings</strong> (Checkout by default, or your business name if you have a permanent settlement account). No per-request override.
                        </p>

                        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-6">
                            <p class="text-sm text-emerald-900 font-semibold mb-2">Validate account name before payout</p>
                            <p class="text-sm text-emerald-900 mb-2">
                                Nigerian bank transfers fail when the <strong>account number</strong>, <strong>bank code</strong>, or <strong>account name</strong> is wrong.
                                Call <code class="bg-emerald-100 px-1 rounded">POST /validate-account</code> first — it returns the verified <code class="bg-emerald-100 px-1 rounded">account_name</code> from the bank.
                            </p>
                            <p class="text-sm text-emerald-900">
                                <strong>Recommended:</strong> when a user adds a payout destination in your app, call validate once, store the returned <code class="bg-emerald-100 px-1 rounded">account_name</code> + <code class="bg-emerald-100 px-1 rounded">bank_code</code>, then send those same values on <code class="bg-emerald-100 px-1 rounded">POST /withdrawal</code>.
                                You can also validate on every payout if you do not store beneficiaries.
                                <code class="bg-emerald-100 px-1 rounded">POST /withdrawal</code> rejects mismatched names with <code class="bg-emerald-100 px-1 rounded">422</code> before money is sent.
                            </p>
                        </div>

                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
                            <p class="text-sm text-amber-900 font-semibold mb-2">Do not batch withdrawals with a cron</p>
                            <p class="text-sm text-amber-900 mb-2">
                                Do <strong>not</strong> run a scheduled job that pushes many customer withdrawals at once. That hits the <strong>1-minute per-business cooldown</strong>, Laravel’s 60 requests/minute API limit, and the bank rail together — most of the batch will return <code class="bg-amber-100 px-1 rounded">429</code> or fail.
                            </p>
                            <p class="text-sm text-amber-900">
                                <strong>Recommended:</strong> let each customer tap Withdraw in <em>your</em> app. Your backend then calls <code class="bg-amber-100 px-1 rounded">POST /api/v1/withdrawal</code> once for that person (amount + their account number + bank). Spread-out, customer-initiated payouts are what this API is for.
                            </p>
                        </div>

                        <div class="space-y-8">
                            <div class="border-l-4 border-emerald-500 pl-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="endpoint-badge badge-get">GET</span>
                                    <code class="text-lg font-mono text-gray-900">/banks</code>
                                </div>
                                <p class="text-gray-700 mb-3">NIP bank list. Use <code class="bg-gray-100 px-1 rounded">code</code> as <code class="bg-gray-100 px-1 rounded">bank_code</code> on withdrawal. Requires Payout API enabled.</p>
                                <div class="mb-2">
                                    <h4 class="font-semibold text-gray-900 mb-2">Response</h4>
                                    <div class="code-block">
                                        <pre><code>{
  "success": true,
  "data": [
    { "code": "000058", "name": "Guaranty Trust Bank" }
  ]
}</code></pre>
                                    </div>
                                </div>
                            </div>

                            <div class="border-l-4 border-emerald-500 pl-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="endpoint-badge badge-post">POST</span>
                                    <code class="text-lg font-mono text-gray-900">/validate-account</code>
                                </div>
                                <p class="text-gray-700 mb-3">Name enquiry: verify a 10-digit NUBAN + NIP <code class="bg-gray-100 px-1 rounded">bank_code</code> and get the exact <code class="bg-gray-100 px-1 rounded">account_name</code> to use on withdrawal. Requires Payout API enabled.</p>
                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-900 mb-2">Request Body</h4>
                                    <div class="code-block">
                                        <pre><code>{
  "account_number": "0123456789",
  "bank_code": "000058",
  "bank_name": "Guaranty Trust Bank"
}</code></pre>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <h4 class="font-semibold text-gray-900 mb-2">Response (200)</h4>
                                    <div class="code-block">
                                        <pre><code>{
  "success": true,
  "message": "Account verified. Store account_name and use it unchanged on POST /withdrawal.",
  "data": {
    "account_number": "0123456789",
    "account_name": "JANE DOE",
    "bank_code": "000058",
    "bank_name": "Guaranty Trust Bank"
  }
}</code></pre>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600">On failure returns <code class="bg-gray-100 px-1 rounded">422</code> with <code class="bg-gray-100 px-1 rounded">success: false</code> — invalid account, wrong bank, or verification temporarily unavailable.</p>
                            </div>

                            <div class="border-l-4 border-blue-500 pl-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="endpoint-badge badge-get">GET</span>
                                    <code class="text-lg font-mono text-gray-900">/balance</code>
                                </div>
                                <p class="text-gray-700 mb-3">Current ledger balance and available balance (includes approved overdraft if any).</p>
                                <div class="code-block">
                                    <pre><code>{
  "success": true,
  "data": {
    "balance": 125000.00,
    "available_balance": 125000.00,
    "currency": "NGN"
  }
}</code></pre>
                                </div>
                            </div>

                            <div class="border-l-4 border-blue-500 pl-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="endpoint-badge badge-post">POST</span>
                                    <code class="text-lg font-mono text-gray-900">/withdrawal</code>
                                </div>
                                <p class="text-gray-700 mb-4">Pays out immediately when the bank accepts the transfer. Minimum ₦100. One successful or attempted payout per business every <strong>1 minute</strong>.</p>

                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-900 mb-2">Request Body</h4>
                                    <div class="code-block">
                                        <pre><code>{
  "amount": 5000,
  "account_number": "0123456789",
  "account_name": "Jane Doe",
  "bank_name": "Guaranty Trust Bank",
  "bank_code": "000058"
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
                                                    <td class="px-4 py-3 text-gray-700">number</td>
                                                    <td class="px-4 py-3 text-gray-700"><span class="text-red-600 font-semibold">Yes</span></td>
                                                    <td class="px-4 py-3 text-gray-700">Naira to send. Minimum 100. Must be ≤ available balance.</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">account_number</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700"><span class="text-red-600 font-semibold">Yes</span></td>
                                                    <td class="px-4 py-3 text-gray-700">10-digit NUBAN</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">account_name</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700"><span class="text-red-600 font-semibold">Yes</span></td>
                                                    <td class="px-4 py-3 text-gray-700">Name on the destination account — must match <code>POST /validate-account</code> (store the verified name or validate each time)</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">bank_name</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700"><span class="text-red-600 font-semibold">Yes</span></td>
                                                    <td class="px-4 py-3 text-gray-700">Bank name (from <code>GET /banks</code>)</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">bank_code</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">Recommended</td>
                                                    <td class="px-4 py-3 text-gray-700">NIP 6-digit code from <code>GET /banks</code>. Required if the name cannot be matched.</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">notes</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">No</td>
                                                    <td class="px-4 py-3 text-gray-700">Internal note (max 1000)</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-4 py-3 font-mono text-gray-900">bank_narration</td>
                                                    <td class="px-4 py-3 text-gray-700">string</td>
                                                    <td class="px-4 py-3 text-gray-700">No</td>
                                                    <td class="px-4 py-3 text-gray-700">Optional text on the bank statement (max 255)</td>
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
  "message": "Transfer completed successfully.",
  "data": {
    "id": 41,
    "amount": 5000,
    "status": "processed",
    "source": "payout_api",
    "payout_status": "successful",
    "payout_reference": "wd_41_abcdefghij",
    "payout_response_message": "Transfer completed successfully.",
    "account_number": "0123456789",
    "account_name": "Jane Doe",
    "bank_name": "Guaranty Trust Bank",
    "created_at": "2026-08-13T10:00:00.000000Z",
    "processed_at": "2026-08-13T10:00:01.000000Z"
  }
}</code></pre>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600 mb-2"><code class="bg-gray-100 px-1 rounded">payout_status</code> is <code class="bg-gray-100 px-1 rounded">successful</code>, <code class="bg-gray-100 px-1 rounded">pending</code>, or <code class="bg-gray-100 px-1 rounded">failed</code>. Balance is decremented only when the bank transfer succeeds. Failed or pending rows stay <code class="bg-gray-100 px-1 rounded">status: pending</code> for admin follow-up. <code class="bg-gray-100 px-1 rounded">message</code> and <code class="bg-gray-100 px-1 rounded">payout_response_message</code> are Checkout-safe copy (provider errors are not forwarded to your app).</p>
                            </div>

                            <div class="border-l-4 border-blue-500 pl-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="endpoint-badge badge-get">GET</span>
                                    <code class="text-lg font-mono text-gray-900">/withdrawals</code>
                                </div>
                                <p class="text-gray-700 mb-3">Paginated history. Optional query <code class="bg-gray-100 px-1 rounded">?status=processed</code> and <code class="bg-gray-100 px-1 rounded">per_page</code> (default 15). Poll this after a pending payout.</p>
                            </div>
                        </div>

                        <div class="mt-6 overflow-x-auto">
                            <h4 class="font-semibold text-gray-900 mb-2">Errors</h4>
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-gray-700 font-semibold">HTTP</th>
                                        <th class="px-4 py-3 text-left text-gray-700 font-semibold">Typical cause</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr>
                                        <td class="px-4 py-3 font-mono">403</td>
                                        <td class="px-4 py-3 text-gray-700">Payout API not enabled for this business</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-mono">400</td>
                                        <td class="px-4 py-3 text-gray-700">Insufficient balance (<code>available_balance</code> in the body)</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-mono">422</td>
                                        <td class="px-4 py-3 text-gray-700">Validation, or bank_code could not be resolved</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-mono">429</td>
                                        <td class="px-4 py-3 text-gray-700">1-minute cooldown, submit lock, or API rate limit</td>
                                    </tr>
                                </tbody>
                            </table>
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
                                <p class="text-gray-700 mb-4"><strong>Website event name does not change.</strong> <code class="bg-gray-100 px-1 rounded">event</code> is always <code class="bg-gray-100 px-1 rounded">payment.approved</code>. Payer identity is duplicated under a few aliases so POS / Pay at Shop clients can read either naming style. Treat aliases as the same value.</p>
                                <div class="code-block">
                                    <pre><code>{
  "event": "payment.approved",
  "transaction_id": "TXN-1234567890",
  "external_reference": "ORDER-TRACK-123",
  "status": "approved",
  "amount": 5000.00,
  "received_amount": 5000.00,
  "payer_name": "John Doe",
  "payerName": "John Doe",
  "sender_name": "John Doe",
  "bank": "GTBank",
  "bank_name": "GTBank",
  "payer_bank": "GTBank",
  "payer_account": "0123456789",
  "payer_account_number": "0123456789",
  "sender_account": "0123456789",
  "payer": {
    "name": "John Doe",
    "account": "0123456789",
    "bank": "GTBank"
  },
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
  "payment_method": "bank_transfer",
  "email_data": {},
  "developer_program_partner_business_id": 42,
  "developer_program_partner_share_amount": 25.00,
  "developer_program_partner_share_percent_effective": 25,
  "developer_program_fee_share_base_description": "CheckoutPay's transaction fee revenue on qualifying attributed volume"
}</code></pre>
                                </div>
                                <p class="text-sm text-gray-600 mt-2 mb-4"><strong>Core fields:</strong> <code>event</code> (<code>payment.approved</code>), <code>transaction_id</code>, <code>external_reference</code> (when set on the payment, e.g. WhatsApp wallet <code>pay/start</code> <code>order_reference</code> or card checkout <code>payment_reference</code>), <code>status</code>, <code>amount</code> (requested), <code>received_amount</code> (actual received; use for reconciliation), <code>payer_name</code>, <code>bank</code>, <code>payer_account_number</code>, <code>account_number</code> (your virtual account; <code>null</code> for card), <code>is_mismatch</code>, <code>mismatch_reason</code>, <code>charges</code>, <code>timestamp</code>, <code>payment_method</code> (<code>bank_transfer</code>, <code>whatsapp_wallet</code>, or <code>card</code>, nullable), <code>email_data</code> (optional raw email info). Developer program (nullable): <code>developer_program_partner_business_id</code>, <code>developer_program_partner_share_amount</code>, <code>developer_program_partner_share_percent_effective</code>, <code>developer_program_fee_share_base_description</code>—see <a href="#developer-program" class="text-primary underline">Developer program</a>.</p>
                                <div class="overflow-x-auto mb-4">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-700">Canonical field</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-700">Aliases (same value)</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 text-gray-700">
                                            <tr><td class="px-4 py-2 font-mono">payer_name</td><td class="px-4 py-2 font-mono">payerName, sender_name, payer.name</td></tr>
                                            <tr><td class="px-4 py-2 font-mono">payer_account_number</td><td class="px-4 py-2 font-mono">payer_account, sender_account, payer.account</td></tr>
                                            <tr><td class="px-4 py-2 font-mono">bank</td><td class="px-4 py-2 font-mono">bank_name, payer_bank, payer.bank</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 mb-2">
                                    <p class="text-sm text-slate-800 mb-2"><strong>Pay at Shop (Cheko) only.</strong> If this approved payment is linked to an in-store broadcast session, these extra fields are included. Existing website handlers can ignore them.</p>
                                    <ul class="list-disc list-inside text-sm text-slate-800 space-y-1">
                                        <li><code>session_id</code> — Pay at Shop session UUID</li>
                                        <li><code>reference</code> — same as <code>transaction_id</code> (or <code>external_reference</code> if no transaction id)</li>
                                        <li><code>broadcast_event</code> — always <code>payment.confirmed</code> (this is <em>not</em> a replacement for <code>event</code>)</li>
                                    </ul>
                                </div>
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

                            <!-- Card collection example -->
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-3">Card collection (PHP)</h3>
                                <p class="text-gray-700 text-sm mb-3">Requires <strong>Card payments</strong> enabled on the business. Full samples (PHP, JavaScript, Python) and the card webhook are under <a href="#card-payments" class="text-primary underline">Card collection</a>.</p>
                                <div class="code-block">
                                    <pre><code>$apiKey = 'pk_your_api_key_here';
$apiUrl = '{{ url('/api/v1') }}';

$data = [
    'amount' => 200.00,
    'payment_method' => 'card',
    'email' => 'customer@example.com',
    'payer_name' => 'Jane Doe',
    'webhook_url' => 'https://yourwebsite.com/webhook/payment-status',
];

$ch = curl_init($apiUrl . '/payment-request');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-Key: ' . $apiKey,
]);

$result = json_decode(curl_exec($ch), true);
curl_close($ch);

$checkoutUrl = $result['data']['card_checkout']['checkout_url'] ?? null;
if (!empty($result['success']) &amp;&amp; $checkoutUrl) {
    header('Location: ' . $checkoutUrl);
    exit;
}</code></pre>
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

if (($payload['event'] ?? '') === 'payment.approved') {
    $transactionId = $payload['transaction_id'] ?? null;
    $method = $payload['payment_method'] ?? null; // bank_transfer | whatsapp_wallet | card
    $payerName = $payload['payer_name']
        ?? $payload['payerName']
        ?? ($payload['payer']['name'] ?? null);
    $received = $payload['received_amount'] ?? $payload['amount'] ?? 0;

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
                            <p class="text-gray-700">
                                <strong>Payouts</strong> (<code class="bg-gray-100 px-1 py-0.5 rounded">POST /withdrawal</code>) also have a <strong>1-minute cooldown per business</strong>. Let customers trigger their own withdrawals instead of pushing a scheduled batch.
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

    @include('partials.faq-section', [
        'category' => 'api',
        'title' => 'Payment gateway API FAQs',
    ])
@endsection

@push('scripts')
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
@endpush

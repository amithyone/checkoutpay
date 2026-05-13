@extends('layouts.business')

@section('title', 'API Documentation')
@section('page-title', 'API Documentation')

@section('content')
<div class="space-y-6 pb-20 lg:pb-0">
    <!-- Quick Links -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">API Documentation</h3>
                <p class="text-sm text-gray-600 mt-1">Complete guide to integrating with CheckoutPay API</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('business.keys.index') }}" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    <i class="fas fa-key mr-2"></i> View API Keys
                </a>
            </div>
        </div>
    </div>

    <!-- Your API Key Quick Reference -->
    <div class="bg-gradient-to-r from-primary to-primary/90 rounded-xl shadow-sm border border-primary/20 p-4 lg:p-6 text-white">
        <h3 class="text-lg font-semibold mb-3">
            <i class="fas fa-key mr-2"></i> Your API Key
        </h3>
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
            <div class="flex-1 bg-white/10 backdrop-blur-sm rounded-lg p-3 border border-white/20">
                <code class="text-sm break-all font-mono">{{ $business->api_key }}</code>
            </div>
            <button onclick="copyApiKey()" class="px-4 py-2 bg-white text-primary rounded-lg hover:bg-gray-100 font-medium text-sm whitespace-nowrap">
                <i class="fas fa-copy mr-2"></i> Copy
            </button>
        </div>
        <p class="text-sm text-primary-100 mt-3">
            <i class="fas fa-info-circle mr-1"></i>
            Store in <code class="bg-white/20 px-1 py-0.5 rounded">.env</code> as <code class="bg-white/20 px-1 py-0.5 rounded">CHECKOUT_API_KEY</code> (never commit real keys). Include in the <code class="bg-white/20 px-1 py-0.5 rounded">X-API-Key</code> header for all API requests.
        </p>
    </div>

    <!-- WordPress Plugin -->
    <div class="bg-gradient-to-r from-purple-50 to-purple-100 border border-purple-200 rounded-xl p-4 lg:p-6">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fab fa-wordpress text-purple-600 text-2xl"></i>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-base font-semibold text-purple-900 mb-1">WordPress / WooCommerce Plugin</h3>
                <p class="text-sm text-purple-700 mb-3">Quick integration for WooCommerce stores. Install our plugin and start accepting payments in minutes.</p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ asset('downloads/checkoutpay-gateway.zip') }}" download class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-medium">
                        <i class="fas fa-download mr-2"></i> Download Plugin
                    </a>
                </div>
                <div class="mt-3 p-2 bg-white/50 rounded-lg">
                    <p class="text-xs text-purple-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Version:</strong> 1.0.0 | <strong>Requires:</strong> WordPress 5.0+, WooCommerce 5.0+
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Developer program -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">
            <i class="fas fa-handshake mr-2 text-primary"></i> Developer program (partner attribution)
        </h3>
        <p class="text-sm text-gray-600 mb-3">Attribute payments to an approved developer partner (their CheckoutPay <strong>Business ID</strong> = <code>businesses.id</code>). Optional on <strong>POST {{ url('/api/v1/payment-request') }}</strong> as <code class="bg-gray-100 px-1 rounded text-xs">developer_program_partner_business_id</code> or alias <code class="bg-gray-100 px-1 rounded text-xs">devprogram</code>. Omitted = unchanged behavior. Partner must have an approved developer program application and cannot be your own business.</p>
        <p class="text-sm text-gray-600 mb-3">When the payment is <strong>approved</strong>, the partner may receive a credit to their <strong>business balance</strong>: a percentage of platform <strong>fees</strong> on that payment (<code>charges.total</code> in the webhook), using the global default and/or per-partner override set by admins—not a deduction from your <code>business_receives</code>.</p>
        <h4 class="text-sm font-semibold text-gray-800 mb-2">Where partner fields apply</h4>
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full text-xs text-left">
                <thead class="bg-gray-50 text-gray-700">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Flow</th>
                        <th class="px-3 py-2 font-semibold">Partner ID on create</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-gray-700">
                    <tr><td class="px-3 py-2">REST <code class="bg-gray-100 rounded px-1">POST /api/v1/payment-request</code></td><td class="px-3 py-2">Optional</td></tr>
                    <tr><td class="px-3 py-2">Hosted checkout, invoice links, tickets, membership, rentals (built-in flows)</td><td class="px-3 py-2">Not supported—use the REST payment request for attribution</td></tr>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-600 mt-3"><strong>WordPress plugin:</strong> store the partner Business ID in settings and send the same JSON key on <code>payment-request</code> only.</p>
    </div>

    <!-- API Base URL -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-server mr-2 text-primary"></i> API Base URL
        </h3>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">API base for this installation</label>
            <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                <code class="text-sm text-gray-900 break-all">{{ url('/api/v1') }}</code>
            </div>
        </div>
        <p class="text-xs text-gray-600 mt-3">
            <i class="fas fa-info-circle mr-1"></i>
            Use this host in production integrations (or set <code class="bg-gray-100 px-1 py-0.5 rounded">APP_URL</code> / <code class="bg-gray-100 px-1 py-0.5 rounded">CHECKOUT_BASE_URL</code> in <code class="bg-gray-100 px-1 py-0.5 rounded">.env</code> on your server so it matches your live checkout domain).
        </p>
    </div>

    <!-- Quick Start Guide -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-rocket mr-2 text-primary"></i> Quick Start
        </h3>
        
        <div class="space-y-6">
            <!-- Create Payment Request -->
            <div>
                <h4 class="text-base font-semibold text-gray-900 mb-3">1. Create Payment Request</h4>
                <p class="text-sm text-gray-600 mb-2">Use <strong>POST</strong> only. Opening this path in a browser (GET) returns <strong>405 Method Not Allowed</strong>.</p>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-xs text-gray-100"><code>POST {{ url('/api/v1/payment-request') }}
Content-Type: application/json
X-API-Key: {{ $business->api_key }}

{
  "name": "John Doe",
  "amount": 5000.00,
  "service": "PRODUCT-123",
  "webhook_url": "https://yourwebsite.com/webhook/checkout",
  "developer_program_partner_business_id": 42
}</code></pre>
                </div>
                <p class="text-xs text-gray-600 mt-2">You may send <code class="bg-gray-800 text-gray-100 px-1 rounded">payer_name</code> instead of <code class="bg-gray-800 text-gray-100 px-1 rounded">name</code>. <code class="bg-gray-800 text-gray-100 px-1 rounded">webhook_url</code> must be on a domain you have approved in the dashboard.</p>
                <p class="text-xs text-gray-600 mt-2">Optional <code class="bg-gray-800 text-gray-100 px-1 rounded">developer_program_partner_business_id</code> (integer, your partner developer&rsquo;s CheckoutPay Business ID) attributes the payment to the developer program when present; omit when not used. The CheckoutPay WordPress plugin should use this same key from its settings. Alias: <code class="bg-gray-800 text-gray-100 px-1 rounded">devprogram</code>. The partner must have an approved developer program application and cannot be your own business.</p>
                <p class="text-sm font-medium text-gray-700 mt-3 mb-1">Expected response (201 Created)</p>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-xs text-gray-100"><code>{
  "success": true,
  "message": "Payment request created successfully",
  "data": {
    "transaction_id": "TXN-1234567890-abc123",
    "amount": 5000.00,
    "payer_name": "john doe",
    "webhook_url": "https://yourwebsite.com/webhook/checkout",
    "account_number": "1234567890",
    "account_name": "Your Business Name",
    "bank_name": "GTBank",
    "status": "pending",
    "expires_at": "2024-01-02T12:00:00.000000Z",
    "created_at": "2024-01-01T12:00:00.000000Z",
    "charges": {
      "percentage": 50.00,
      "fixed": 100.00,
      "total": 150.00,
      "paid_by_customer": false,
      "amount_to_pay": 5000.00,
      "business_receives": 4850.00
    },
    "website": { "id": 1, "url": "https://yourwebsite.com" }
  }
}</code></pre>
                </div>
                <p class="text-xs text-gray-600 mt-2">Display <code>account_number</code>, <code>account_name</code>, <code>bank_name</code> and <code>transaction_id</code> to your customer.</p>
            </div>

            <!-- Check Payment Status -->
            <div>
                <h4 class="text-base font-semibold text-gray-900 mb-3">2. Check Payment Status</h4>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-xs text-gray-100"><code>GET {{ url('/api/v1/payment/{transactionId}') }}
X-API-Key: {{ $business->api_key }}</code></pre>
                </div>
            </div>

            <!-- Correct transaction amount (wrong amount sent) -->
            <div>
                <h4 class="text-base font-semibold text-gray-900 mb-3">2a. Correct transaction amount (optional)</h4>
                <p class="text-sm text-gray-600 mb-2">If you sent the wrong amount, update the pending payment to the actual amount the customer paid. We'll find your transaction with the new amount and approve it instead of refunding.</p>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-xs text-gray-100"><code>PATCH {{ url('/api/v1/payment/{transactionId}/amount') }}
Content-Type: application/json
X-API-Key: {{ $business->api_key }}

{
  "new_amount": 7500.00
}</code></pre>
                </div>
                <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                    <p class="text-xs text-amber-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        Only <strong>pending</strong>, non-expired payments can be updated. Then use <strong>GET /payment/{transactionId}</strong> to check status or wait for the webhook.
                    </p>
                </div>
            </div>

            <!-- Webhook Notifications -->
            <div>
                <h4 class="text-base font-semibold text-gray-900 mb-3">3. Receive Webhook Notifications</h4>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-xs text-gray-100"><code>POST https://yourwebsite.com/webhook/checkout
Content-Type: application/json

{
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
  "charges": { "percentage": 50, "fixed": 50, "total": 100, "business_receives": 4900 },
  "timestamp": "2024-01-15T10:30:00Z",
  "email_data": {},
  "developer_program_partner_business_id": 42,
  "developer_program_partner_share_amount": 25.00,
  "developer_program_partner_share_percent_effective": 25,
  "developer_program_fee_share_base_description": "CheckoutPay's transaction fee revenue on qualifying attributed volume"
}</code></pre>
                </div>
                <p class="text-xs text-gray-600 mt-2">Use <code>transaction_id</code> to identify the payment; use <code>external_reference</code> when present (e.g. your <code>order_reference</code> from WhatsApp wallet <code>pay/start</code>). Use <code>received_amount</code> and <code>charges.business_receives</code> for reconciliation. When you use the developer program, also read <code>developer_program_partner_business_id</code> and <code>developer_program_partner_share_amount</code> (nullable).</p>
                <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-xs text-green-800">
                        <i class="fas fa-check-circle mr-1"></i>
                        Configure your webhook URL in <a href="{{ route('business.settings.index') }}" class="underline font-medium">Settings</a> to receive automatic notifications.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- WhatsApp wallet merchant API -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6" id="whatsapp-wallet">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">
            <i class="fab fa-whatsapp mr-2 text-green-600"></i> WhatsApp wallet (merchant API)
        </h3>
        <p class="text-sm text-gray-600 mb-4">
            Requires admin to <strong>enable WhatsApp wallet API</strong> on your business. Authenticate with <code class="bg-gray-100 px-1 rounded text-xs">X-API-Key</code> like other routes. Base: <code class="bg-gray-100 px-1 rounded text-xs">{{ url('/api/v1/whatsapp-wallet') }}</code>
        </p>

        <div class="space-y-6">
            <div>
                <h4 class="text-base font-semibold text-gray-900 mb-2">POST …/lookup — wallet balance</h4>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-xs text-gray-100"><code>POST {{ url('/api/v1/whatsapp-wallet/lookup') }}
Content-Type: application/json
X-API-Key: {{ $business->api_key }}

{ "phone": "08012345678" }</code></pre>
                </div>
            </div>

            <div>
                <h4 class="text-base font-semibold text-gray-900 mb-2">POST …/ensure — create wallet shell if missing</h4>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-xs text-gray-100"><code>POST {{ url('/api/v1/whatsapp-wallet/ensure') }}
Content-Type: application/json
X-API-Key: {{ $business->api_key }}

{ "phone": "08012345678" }</code></pre>
                </div>
            </div>

            <div>
                <h4 class="text-base font-semibold text-gray-900 mb-2">POST …/send-message — WhatsApp text you compose (e.g. OTP)</h4>
                <p class="text-sm text-gray-600 mb-2">Same API key as orders; no separate Checkout secret per app. Body includes the full <code class="bg-gray-100 px-1 rounded text-xs">message</code> (max 4000 chars).</p>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-xs text-gray-100"><code>POST {{ url('/api/v1/whatsapp-wallet/send-message') }}
Content-Type: application/json
X-API-Key: {{ $business->api_key }}

{
  "phone": "08012345678",
  "message": "Your login code is 123456. Valid 10 minutes."
}</code></pre>
                </div>
            </div>

            <div>
                <h4 class="text-base font-semibold text-gray-900 mb-2">POST …/pay/start — customer pays via WhatsApp + PIN link (recommended)</h4>
                <p class="text-sm text-gray-600 mb-2">Checkout WhatsApps the customer a summary and secure link. <code class="bg-gray-100 px-1 rounded text-xs">webhook_url</code> must match your saved business or approved website webhook URL exactly. After PIN success you receive <code class="bg-gray-100 px-1 rounded text-xs">payment.approved</code> including <code class="bg-gray-100 px-1 rounded text-xs">external_reference</code> = your <code class="bg-gray-100 px-1 rounded text-xs">order_reference</code>.</p>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-xs text-gray-100"><code>POST {{ url('/api/v1/whatsapp-wallet/pay/start') }}
Content-Type: application/json
X-API-Key: {{ $business->api_key }}

{
  "phone": "08012345678",
  "amount": 2500.00,
  "order_reference": "ORDER-TRACK-123",
  "order_summary": "2x Jollof rice\n1x Zobo\nDelivery: Surulere",
  "payer_name": "Ada Customer",
  "webhook_url": "https://your-app.example.com/api/webhooks/checkout/payment",
  "idempotency_key": "order-123-wallet-try-1"
}</code></pre>
                </div>
                <p class="text-xs text-gray-600 mt-2"><strong>201 response</strong> includes <code class="bg-gray-800 text-gray-100 px-1 rounded">confirm_url</code> (same link sent on WhatsApp). Customer opens it and enters their 4-digit wallet PIN.</p>
            </div>

            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                <p class="text-sm text-amber-900"><strong>No PIN-less debit.</strong> Wallet charges to your business only run after the customer opens the secure link from WhatsApp and enters their wallet PIN (<strong>pay/start</strong> only).</p>
            </div>
        </div>
    </div>

    <!-- Consumer mobile wallet API (end-user app) -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6" id="consumer-wallet-api">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">
            <i class="fas fa-mobile-alt mr-2 text-primary"></i> Consumer wallet API (Android / iOS)
        </h3>
        <p class="text-sm text-gray-600 mb-4">
            End-user apps authenticate with <strong>WhatsApp OTP</strong> (code sent to the same number as the wallet) and a <code class="bg-gray-100 px-1 rounded text-xs">Bearer</code> token from Sanctum.
            Base path: <code class="bg-gray-100 px-1 rounded text-xs">{{ url('/api/v1/consumer') }}</code>.
            This uses the same <code class="bg-gray-100 px-1 rounded text-xs">whatsapp_wallets</code> rows as the WhatsApp bot.
        </p>
        <div class="space-y-4 text-sm text-gray-700">
            <p><strong>1. Request OTP:</strong> <code class="text-xs bg-gray-100 px-1 rounded">POST …/auth/otp/request</code> JSON <code class="text-xs">{"phone":"080…"}</code></p>
            <p><strong>2. Verify &amp; token:</strong> <code class="text-xs bg-gray-100 px-1 rounded">POST …/auth/otp/verify</code> JSON <code class="text-xs">{"phone":"080…","code":"123456"}</code> → returns <code class="text-xs">token</code>; send <code class="text-xs">Authorization: Bearer &lt;token&gt;</code> on all following calls.</p>
            <p><strong>3. Wallet:</strong> <code class="text-xs bg-gray-100 px-1 rounded">GET …/wallet</code>, <code class="text-xs">POST …/wallet/ensure</code>, <code class="text-xs">GET …/wallet/transactions</code>, <code class="text-xs">POST …/wallet/topup/virtual-account</code></p>
            <p><strong>4. PIN:</strong> <code class="text-xs bg-gray-100 px-1 rounded">POST …/wallet/pin</code> (first-time), <code class="text-xs">PUT …/wallet/pin</code> (change). Debits require 4-digit <code class="text-xs">pin</code> on transfer/VTU routes.</p>
            <p><strong>5. Transfers:</strong> <code class="text-xs bg-gray-100 px-1 rounded">POST …/transfers/p2p</code> (<code class="text-xs">to_phone</code>, <code class="text-xs">amount</code>, <code class="text-xs">pin</code>), <code class="text-xs">POST …/transfers/bank</code> (account + bank + <code class="text-xs">pin</code>), <code class="text-xs">GET …/banks/name-enquiry</code></p>
            <p><strong>6. VTU:</strong> <code class="text-xs bg-gray-100 px-1 rounded">GET …/vtu/networks</code> (returns <code class="text-xs">networks</code>, <code class="text-xs">configured</code>, airtime limits), <code class="text-xs">GET …/vtu/data-plans</code>, <code class="text-xs">POST …/vtu/airtime</code>, <code class="text-xs">POST …/vtu/data</code>. Wallet <code class="text-xs">GET …/wallet</code> includes a <code class="text-xs">vtu</code> object for app gating.</p>
            <p><strong>7. In-app wallet chat:</strong> Primary: <code class="text-xs bg-gray-100 px-1 rounded">POST …/wallet/conversation</code> JSON <code class="text-xs">{"text":"…"}</code> (same <code class="text-xs">WhatsappSession</code> as WhatsApp; optional <code class="text-xs">POST …/wallet/transfer/confirm-web-token</code> for staged debits). Legacy apps may still use <code class="text-xs bg-gray-100 px-1 rounded">GET …/chat/messages</code> / <code class="text-xs">POST …/chat/messages</code> with <code class="text-xs">{"body":"…"}</code> (thread + polling). Server-to-server support injection <code class="text-xs">POST {{ url('/api/v1/internal/consumer-chat/reply') }}</code> is retired (HTTP 410).</p>
            <p><strong>8. Tier 2 KYC:</strong> <code class="text-xs bg-gray-100 px-1 rounded">GET …/kyc/tier2</code>, <code class="text-xs">POST …/kyc/tier2/personal</code>, <code class="text-xs">POST …/kyc/tier2/business</code></p>
            <p><strong>Errors:</strong> JSON <code class="text-xs">success: false</code> and <code class="text-xs">message</code>; HTTP 401 without/invalid token, 422 validation or business rules, 423 PIN locked, 502 provider failures where applicable.</p>
            <p class="text-xs text-gray-500">Logout: <code class="bg-gray-100 px-1 rounded">POST …/auth/logout</code> (Bearer).</p>
        </div>
    </div>

    <!-- Code Examples -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-code mr-2 text-primary"></i> Code Examples
        </h3>
        <p class="text-sm text-gray-600 mb-4">
            In production, use <code class="bg-gray-100 px-1 py-0.5 rounded text-xs">CHECKOUT_API_KEY</code> and <code class="bg-gray-100 px-1 py-0.5 rounded text-xs">CHECKOUT_BASE_URL</code> from your environment instead of hardcoding.
        </p>
        <div class="space-y-6">
            <!-- PHP Example -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-base font-semibold text-gray-900">PHP</h4>
                    <button onclick="copyCode('php-example')" class="text-xs text-primary hover:underline">
                        <i class="fas fa-copy mr-1"></i> Copy
                    </button>
                </div>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre id="php-example" class="text-xs text-gray-100"><code>$apiKey = '{{ $business->api_key }}';
$apiUrl = '{{ url('/api/v1/payment-request') }}';

$data = [
    'name' => 'John Doe',
    'amount' => 5000.00,
    'service' => 'PRODUCT-123',
    'webhook_url' => 'https://yourwebsite.com/webhook/checkout'
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-Key: ' . $apiKey
]);

$response = curl_exec($ch);
$result = json_decode($response, true);

if ($result['success']) {
    $accountNumber = $result['data']['account_number'];
    $transactionId = $result['data']['transaction_id'];
    // Display account number to customer
}</code></pre>
                </div>
            </div>

            <!-- JavaScript Example -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-base font-semibold text-gray-900">JavaScript (Node.js)</h4>
                    <button onclick="copyCode('js-example')" class="text-xs text-primary hover:underline">
                        <i class="fas fa-copy mr-1"></i> Copy
                    </button>
                </div>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre id="js-example" class="text-xs text-gray-100"><code>const apiKey = '{{ $business->api_key }}';
const apiUrl = '{{ url('/api/v1/payment-request') }}';

const response = await fetch(apiUrl, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-API-Key': apiKey
    },
    body: JSON.stringify({
        name: 'John Doe',
        amount: 5000.00,
        service: 'PRODUCT-123',
        webhook_url: 'https://yourwebsite.com/webhook/checkout'
    })
});

const result = await response.json();

if (result.success) {
    const { account_number, transaction_id } = result.data;
    // Display account number to customer
}</code></pre>
                </div>
            </div>

            <!-- Python Example -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-base font-semibold text-gray-900">Python</h4>
                    <button onclick="copyCode('python-example')" class="text-xs text-primary hover:underline">
                        <i class="fas fa-copy mr-1"></i> Copy
                    </button>
                </div>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre id="python-example" class="text-xs text-gray-100"><code>import requests

api_key = '{{ $business->api_key }}'
api_url = '{{ url('/api/v1/payment-request') }}'

headers = {
    'Content-Type': 'application/json',
    'X-API-Key': api_key
}

data = {
    'name': 'John Doe',
    'amount': 5000.00,
    'service': 'PRODUCT-123',
    'webhook_url': 'https://yourwebsite.com/webhook/checkout'
}

response = requests.post(api_url, json=data, headers=headers)
result = response.json()

if result['success']:
    account_number = result['data']['account_number']
    transaction_id = result['data']['transaction_id']
    # Display account number to customer</code></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Full Documentation -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-book mr-2 text-primary"></i> Complete Documentation
        </h3>
        <div class="prose prose-sm max-w-none">
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 overflow-x-auto">
                <div class="text-sm text-gray-700 whitespace-pre-wrap font-mono leading-relaxed">{{ $documentation }}</div>
            </div>
        </div>
        <div class="mt-4 p-3 bg-gray-50 border border-gray-200 rounded-lg">
            <p class="text-xs text-gray-600">
                <i class="fas fa-book mr-1"></i>
                The documentation above is the complete reference. Scroll up for Quick Start and code examples.
            </p>
        </div>
    </div>
</div>

@push('scripts')
<script>
function copyApiKey() {
    const apiKey = '{{ $business->api_key }}';
    navigator.clipboard.writeText(apiKey).then(function() {
        showNotification('API key copied to clipboard!');
    }).catch(function(err) {
        alert('Failed to copy. Please copy manually.');
    });
}

function copyCode(elementId) {
    const codeElement = document.getElementById(elementId);
    const text = codeElement.textContent || codeElement.innerText;
    
    navigator.clipboard.writeText(text).then(function() {
        showNotification('Code copied to clipboard!');
    }).catch(function(err) {
        alert('Failed to copy. Please copy manually.');
    });
}

function showNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-3 rounded-lg shadow-lg z-50 flex items-center';
    notification.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.3s';
        setTimeout(() => notification.remove(), 300);
    }, 2000);
}
</script>
@endpush
@endsection

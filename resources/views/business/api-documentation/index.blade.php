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
  "webhook_url": "https://yourwebsite.com/webhook/checkout"
}</code></pre>
                </div>
                <p class="text-xs text-gray-600 mt-2">You may send <code class="bg-gray-800 text-gray-100 px-1 rounded">payer_name</code> instead of <code class="bg-gray-800 text-gray-100 px-1 rounded">name</code>. <code class="bg-gray-800 text-gray-100 px-1 rounded">webhook_url</code> must be on a domain you have approved in the dashboard.</p>
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
  "email_data": {}
}</code></pre>
                </div>
                <p class="text-xs text-gray-600 mt-2">Use <code>transaction_id</code> to identify the payment; use <code>received_amount</code> and <code>charges.business_receives</code> for reconciliation. Response structure is stable.</p>
                <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-xs text-green-800">
                        <i class="fas fa-check-circle mr-1"></i>
                        Configure your webhook URL in <a href="{{ route('business.settings.index') }}" class="underline font-medium">Settings</a> to receive automatic notifications.
                    </p>
                </div>
            </div>
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

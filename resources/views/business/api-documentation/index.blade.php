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
                <a href="https://github.com/amithyone/checkoutpay/blob/main/API_DOCUMENTATION.md" target="_blank" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium">
                    <i class="fab fa-github mr-2"></i> View on GitHub
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
            Include this key in the <code class="bg-white/20 px-1 py-0.5 rounded">X-API-Key</code> header for all API requests
        </p>
    </div>

    <!-- API Base URL -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 lg:p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-server mr-2 text-primary"></i> API Base URL
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Production</label>
                <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                    <code class="text-sm text-gray-900 break-all">https://check-outpay.com/api/v1</code>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Development</label>
                <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                    <code class="text-sm text-gray-900 break-all">{{ url('/api/v1') }}</code>
                </div>
            </div>
        </div>
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
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-xs text-gray-100"><code>POST {{ url('/api/v1/payment-request') }}
Content-Type: application/json
X-API-Key: {{ $business->api_key }}

{
  "name": "John Doe",
  "amount": 5000.00,
  "service": "PRODUCT-123",
  "webhook_url": "https://yourwebsite.com/webhook/payment-status"
}</code></pre>
                </div>
                <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-xs text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Response:</strong> You'll receive an account number and transaction ID to display to your customer.
                    </p>
                </div>
            </div>

            <!-- Check Payment Status -->
            <div>
                <h4 class="text-base font-semibold text-gray-900 mb-3">2. Check Payment Status</h4>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-xs text-gray-100"><code>GET {{ url('/api/v1/payment/{transactionId}') }}
X-API-Key: {{ $business->api_key }}</code></pre>
                </div>
            </div>

            <!-- Webhook Notifications -->
            <div>
                <h4 class="text-base font-semibold text-gray-900 mb-3">3. Receive Webhook Notifications</h4>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-xs text-gray-100"><code>POST https://yourwebsite.com/webhook/payment-status
Content-Type: application/json

{
  "event": "payment.approved",
  "transaction_id": "txn_123456",
  "status": "approved",
  "amount": 5000.00,
  "received_amount": 5000.00,
  "payer_name": "John Doe",
  "timestamp": "2024-01-15T10:30:00Z"
}</code></pre>
                </div>
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
    'webhook_url' => 'https://yourwebsite.com/webhook/payment-status'
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
        webhook_url: 'https://yourwebsite.com/webhook/payment-status'
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
    'webhook_url': 'https://yourwebsite.com/webhook/payment-status'
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
                <i class="fas fa-external-link-alt mr-1"></i>
                For better formatting and the latest updates, view the documentation on 
                <a href="https://github.com/amithyone/checkoutpay/blob/main/API_DOCUMENTATION.md" target="_blank" class="text-primary hover:underline">GitHub</a>.
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

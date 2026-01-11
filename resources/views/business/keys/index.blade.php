@extends('layouts.business')

@section('title', 'API Keys & Integration')
@section('page-title', 'API Keys & Integration')

@section('content')
<div class="space-y-6">
    <!-- API Key -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">API Key</h3>
        
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Your API Key</label>
                <div class="flex items-center gap-2">
                    <input type="text" value="{{ $business->api_key }}" readonly
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 font-mono text-sm"
                        id="api-key-input">
                    <button type="button" onclick="copyApiKey()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                        <i class="fas fa-copy mr-2"></i> Copy
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-500">Keep this key secure. Don't share it publicly.</p>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-sm text-yellow-800">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Warning:</strong> Regenerating your API key will invalidate the current key. Make sure to update it in all your integrations.
                </p>
            </div>

            <form method="POST" action="{{ route('business.settings.regenerate-api-key') }}" onsubmit="return confirm('Are you sure you want to regenerate your API key? This will invalidate your current key.')">
                @csrf
                @method('POST')
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    <i class="fas fa-sync-alt mr-2"></i> Regenerate API Key
                </button>
            </form>
        </div>
    </div>

    <!-- Documentation Link -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 lg:p-6">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fas fa-book text-blue-600 text-xl"></i>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-base font-semibold text-blue-900 mb-1">Complete API Documentation</h3>
                <p class="text-sm text-blue-700 mb-3">View detailed API documentation with examples, endpoints, and integration guides.</p>
                <a href="{{ route('business.api-documentation.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                    <i class="fas fa-arrow-right mr-2"></i> View Documentation
                </a>
            </div>
        </div>
    </div>

    <!-- Integration Guide -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">Quick Integration Guide</h3>
        
        <div class="space-y-6">
            <!-- API Endpoint -->
            <div>
                <h4 class="text-sm font-semibold text-gray-900 mb-2">API Endpoint</h4>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <code class="text-sm text-gray-800">{{ url('/api/v1') }}</code>
                </div>
            </div>

            <!-- Authentication -->
            <div>
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Authentication</h4>
                <p class="text-sm text-gray-600 mb-3">Include your API key in the request header:</p>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <code class="text-sm text-gray-800">X-API-Key: {{ $business->api_key }}</code>
                </div>
            </div>

            <!-- Create Payment Request -->
            <div>
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Create Payment Request</h4>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 overflow-x-auto">
                    <pre class="text-xs text-gray-800"><code>POST {{ url('/api/v1/payment-request') }}
Content-Type: application/json
X-API-Key: {{ $business->api_key }}

{
  "amount": 1000.00,
  "payer_name": "John Doe",
  "expires_in_minutes": 60
}</code></pre>
                </div>
            </div>

            <!-- Check Payment Status -->
            <div>
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Check Payment Status</h4>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 overflow-x-auto">
                    <pre class="text-xs text-gray-800"><code>GET {{ url('/api/v1/payment/{transaction_id}') }}
X-API-Key: {{ $business->api_key }}</code></pre>
                </div>
            </div>

            <!-- Webhook Configuration -->
            <div>
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Webhook Configuration</h4>
                <p class="text-sm text-gray-600 mb-3">Configure your webhook URL in Settings to receive payment notifications automatically.</p>
                <a href="{{ route('business.settings.index') }}" class="text-primary hover:underline text-sm">
                    Go to Settings <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Account Number Request -->
    @if($business->website_approved)
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">Request Account Number</h3>
        
        @if($business->hasAccountNumber())
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-green-800">
                    <i class="fas fa-check-circle mr-2"></i>
                    You already have an active account number assigned.
                </p>
            </div>
            <a href="{{ route('business.profile.index') }}" class="text-primary hover:underline text-sm">
                View Account Numbers <i class="fas fa-arrow-right ml-1"></i>
            </a>
        @else
            <p class="text-sm text-gray-600 mb-4">Request a dedicated account number for your business. This will be assigned after admin approval.</p>
            <form method="POST" action="{{ route('business.keys.request-account-number') }}" onsubmit="return confirm('Are you sure you want to request an account number? This will be reviewed by our team.')">
                @csrf
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    <i class="fas fa-credit-card mr-2"></i> Request Account Number
                </button>
            </form>
        @endif
    </div>
    @else
    <div class="bg-white rounded-lg shadow-sm border border-yellow-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">Request Account Number</h3>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <p class="text-sm text-yellow-800">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong>Website Approval Required:</strong> Your website must be approved before you can request an account number. Please wait for website approval.
            </p>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
function copyApiKey() {
    const input = document.getElementById('api-key-input');
    input.select();
    input.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(input.value).then(function() {
        const message = document.createElement('div');
        message.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
        message.textContent = 'API key copied to clipboard!';
        document.body.appendChild(message);
        
        setTimeout(() => {
            message.remove();
        }, 2000);
    }).catch(function(err) {
        alert('Failed to copy. Please copy manually.');
    });
}
</script>
@endpush
@endsection

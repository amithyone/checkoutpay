@extends('layouts.business')

@section('title', 'Settings')
@section('page-title', 'Settings')

@section('content')
<div class="space-y-6">
    <!-- Websites Portfolio -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">Websites Portfolio</h3>
            <a href="{{ route('business.websites.index') }}" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                <i class="fas fa-cog mr-2"></i> Manage Websites
            </a>
        </div>
        
        @php
            $websites = $business->websites;
            $approvedCount = $business->approvedWebsites->count();
        @endphp
        
        @if($websites->count() > 0)
            <div class="space-y-3 mb-4">
                @foreach($websites->take(3) as $website)
                    <div class="border border-gray-200 rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <a href="{{ $website->website_url }}" target="_blank" 
                                    class="text-primary hover:underline font-medium text-sm">
                                    {{ $website->website_url }}
                                    <i class="fas fa-external-link-alt text-xs ml-1"></i>
                                </a>
                                <div class="mt-1">
                                    @if($website->is_approved)
                                        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                            <i class="fas fa-check-circle mr-1"></i> Approved
                                        </span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                            <i class="fas fa-clock mr-1"></i> Pending
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
                @if($websites->count() > 3)
                    <p class="text-xs text-gray-500 text-center">
                        + {{ $websites->count() - 3 }} more website(s). 
                        <a href="{{ route('business.websites.index') }}" class="text-primary hover:underline">View all</a>
                    </p>
                @endif
            </div>
            
            <div class="border-t pt-4">
                @if($approvedCount > 0)
                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">
                            <i class="fas fa-check-circle mr-2"></i> {{ $approvedCount }} Approved Website(s)
                        </span>
                        <p class="text-sm text-gray-600">You can request account numbers.</p>
                    </div>
                @else
                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 text-sm font-medium bg-yellow-100 text-yellow-800 rounded-full">
                            <i class="fas fa-clock mr-2"></i> Pending Approval
                        </span>
                        <p class="text-sm text-gray-600">Waiting for admin approval.</p>
                    </div>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mt-3">
                        <p class="text-sm text-yellow-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Note:</strong> You need at least one approved website before you can request account numbers. Our team will review your websites and notify you once approved.
                        </p>
                    </div>
                @endif
            </div>
        @else
            <div class="text-center py-6 text-gray-500">
                <i class="fas fa-globe text-3xl mb-2 text-gray-300"></i>
                <p class="text-sm mb-3">No websites added yet.</p>
                <a href="{{ route('business.websites.index') }}" class="text-primary hover:underline text-sm">
                    Add your first website →
                </a>
            </div>
        @endif
    </div>

    <!-- Webhook Settings -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">Webhook Settings</h3>
        
        <form method="POST" action="{{ route('business.settings.update') }}">
            @csrf
            @method('PUT')

            <div>
                <label for="webhook_url" class="block text-sm font-medium text-gray-700 mb-1">Webhook URL</label>
                <input type="url" name="webhook_url" id="webhook_url" value="{{ old('webhook_url', $business->webhook_url) }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                    placeholder="https://your-domain.com/webhook">
                <p class="mt-1 text-xs text-gray-500">We'll send payment notifications to this URL</p>
                @error('webhook_url')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-6">
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    Save Settings
                </button>
            </div>
        </form>
    </div>

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

    <!-- Account Information -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">Account Information</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Business Name</label>
                <p class="text-sm text-gray-900">{{ $business->name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <p class="text-sm text-gray-900">{{ $business->email }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account Status</label>
                <p class="text-sm">
                    @if($business->is_active)
                        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                    @else
                        <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Inactive</span>
                    @endif
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Current Balance</label>
                <p class="text-sm font-semibold text-gray-900">₦{{ number_format($business->balance, 2) }}</p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function copyApiKey() {
    const input = document.getElementById('api-key-input');
    input.select();
    input.setSelectionRange(0, 99999); // For mobile devices
    
    navigator.clipboard.writeText(input.value).then(function() {
        // Show success message
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

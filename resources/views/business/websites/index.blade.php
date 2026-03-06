@extends('layouts.business')

@section('title', 'My Websites')
@section('page-title', 'My Websites')

@section('content')
<div class="space-y-6">
    <!-- Add Website Form -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4">Add New Website</h3>
        
        <form method="POST" action="{{ route('business.websites.store') }}">
            @csrf
            <div class="space-y-3">
                <div>
                    <label for="website_url" class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Website URL</label>
                    <input type="url" name="website_url" id="website_url" 
                        value="{{ old('website_url') }}"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        placeholder="https://yourwebsite.com" required>
                    @error('website_url')
                        <p class="mt-1 text-xs sm:text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="webhook_url" class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Webhook URL (Optional)</label>
                    <div class="flex gap-2">
                        <input type="url" name="webhook_url" id="webhook_url" 
                            value="{{ old('webhook_url') }}"
                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                            placeholder="https://yourwebsite.com/webhook">
                        <button type="button" class="test-webhook-btn px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 hover:bg-gray-100 text-gray-700 whitespace-nowrap" data-input-id="webhook_url" title="Send a test request to verify the URL">
                            <i class="fas fa-paper-plane mr-1"></i> Test
                        </button>
                    </div>
                    <p id="webhook_test_result" class="mt-1 text-xs hidden" aria-live="polite"></p>
                    @error('webhook_url')
                        <p class="mt-1 text-xs sm:text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Notifications will be sent to this URL when payments are approved for this website.</p>
                </div>
                <button type="submit" class="w-full sm:w-auto px-4 sm:px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm">
                    <i class="fas fa-plus mr-2"></i> Add Website
                </button>
            </div>
            <p class="mt-2 text-xs text-gray-500">New websites require admin approval before they can be used for checkout redirects.</p>
        </form>
    </div>

    <!-- Websites List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4">Your Websites</h3>
        
        @if($websites->count() > 0)
            <div class="space-y-4">
                @foreach($websites as $website)
                    <div class="border border-gray-200 rounded-lg p-3 sm:p-4 hover:bg-gray-50">
                        <div class="space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-2">
                                        <a href="{{ $website->website_url }}" target="_blank" 
                                            class="text-primary hover:underline font-medium text-sm sm:text-base break-all">
                                            {{ Str::limit($website->website_url, 50) }}
                                            <i class="fas fa-external-link-alt text-xs ml-1"></i>
                                        </a>
                                        @if($website->is_approved)
                                            <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full self-start">
                                                <i class="fas fa-check-circle mr-1"></i> Approved
                                            </span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full self-start">
                                                <i class="fas fa-clock mr-1"></i> Pending Approval
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Added {{ $website->created_at->format('M d, Y') }}
                                        @if($website->approved_at)
                                            • Approved {{ $website->approved_at->format('M d, Y') }}
                                        @endif
                                    </div>
                                    @if($website->notes)
                                        <div class="mt-2 text-xs text-gray-600 bg-gray-50 p-2 rounded break-words">
                                            <strong>Note:</strong> {{ $website->notes }}
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-shrink-0">
                                    <form method="POST" action="{{ route('business.websites.destroy', $website) }}" 
                                        onsubmit="return confirm('Are you sure you want to remove this website?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg text-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            @if($website->is_approved)
                                <div class="border-t border-gray-200 pt-3">
                                    <form method="POST" action="{{ route('business.websites.update', $website) }}" class="flex flex-col sm:flex-row gap-2">
                                        @csrf
                                        @method('PUT')
                                        <div class="flex-1 min-w-0">
                                            <label for="webhook_url_{{ $website->id }}" class="block text-xs font-medium text-gray-700 mb-1">Webhook URL</label>
                                            <div class="flex gap-2">
                                                <input type="url" name="webhook_url" id="webhook_url_{{ $website->id }}" 
                                                    value="{{ old('webhook_url', $website->webhook_url) }}"
                                                    class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                                                    placeholder="https://yourwebsite.com/webhook">
                                                <button type="button" class="test-webhook-btn px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 hover:bg-gray-100 text-gray-700 whitespace-nowrap" data-input-id="webhook_url_{{ $website->id }}" title="Send a test request to verify the URL">
                                                    <i class="fas fa-paper-plane mr-1"></i> Test
                                                </button>
                                            </div>
                                            <p class="webhook_test_result mt-1 text-xs hidden" aria-live="polite" data-for="webhook_url_{{ $website->id }}"></p>
                                            @error('webhook_url')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="flex items-end">
                                            <button type="submit" class="w-full sm:w-auto px-4 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary/90">
                                                <i class="fas fa-save mr-1"></i> Save
                                            </button>
                                        </div>
                                    </form>
                                    <p class="mt-1 text-xs text-gray-500">Payment notifications will be sent to this URL for this website.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-globe text-4xl mb-3 text-gray-300"></i>
                <p>No websites added yet. Add your first website above.</p>
            </div>
        @endif
    </div>

    <!-- Information Box -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <p class="text-sm text-blue-800">
            <i class="fas fa-info-circle mr-2"></i>
            <strong>Note:</strong> You need at least one approved website before you can request an account number. 
            Approved websites are used to validate return URLs in your checkout integration.
        </p>
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.test-webhook-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var inputId = this.getAttribute('data-input-id');
        var input = document.getElementById(inputId);
        var url = input && input.value ? input.value.trim() : '';
        if (!url) {
            alert('Please enter a webhook URL first.');
            return;
        }
        var resultEl = document.getElementById('webhook_test_result') || document.querySelector('.webhook_test_result[data-for="' + inputId + '"]');
        if (resultEl) {
            resultEl.textContent = 'Sending test…';
            resultEl.classList.remove('hidden', 'text-green-600', 'text-red-600');
            resultEl.classList.add('text-gray-600');
        }
        this.disabled = true;
        fetch('{{ route("business.webhook.test") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ webhook_url: url })
        })
        .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
        .then(function(_ref) {
            var ok = _ref.ok, data = _ref.data;
            if (resultEl) {
                resultEl.classList.remove('text-gray-600');
                resultEl.classList.add(ok ? 'text-green-600' : 'text-red-600');
                resultEl.textContent = data.message || (ok ? 'Webhook delivered successfully.' : 'Request failed.');
                resultEl.classList.remove('hidden');
            } else {
                alert(ok ? (data.message || 'Webhook delivered successfully.') : (data.message || 'Request failed.'));
            }
        })
        .catch(function(err) {
            if (resultEl) {
                resultEl.classList.remove('text-gray-600');
                resultEl.classList.add('text-red-600');
                resultEl.textContent = 'Request failed: ' + (err.message || 'Network error');
                resultEl.classList.remove('hidden');
            } else {
                alert('Request failed: ' + (err.message || 'Network error'));
            }
        })
        .finally(function() { btn.disabled = false; });
    });
});
</script>
@endpush
@endsection

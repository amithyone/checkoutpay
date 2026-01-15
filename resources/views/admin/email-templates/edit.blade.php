@extends('layouts.admin')

@section('title', 'Edit Email Template')
@section('page-title', 'Edit Email Template')

@section('content')
<div class="max-w-6xl mx-auto">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-4">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900">{{ $templateInfo['name'] }}</h2>
            <p class="text-sm text-gray-600 mt-1">{{ $templateInfo['description'] }}</p>
        </div>

        <form action="{{ route('admin.email-templates.update', $template) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="space-y-6">
                <!-- Email Subject -->
                <div>
                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                        Email Subject <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="subject" 
                        name="subject" 
                        value="{{ old('subject', $customSubject) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        required
                    >
                    <p class="text-xs text-gray-500 mt-1">You can use variables like {{ '$appName' }} in the subject</p>
                </div>

                <!-- Use Custom Template Checkbox -->
                <div class="flex items-center space-x-3 p-4 bg-gray-50 rounded-lg">
                    <input 
                        type="checkbox" 
                        id="use_custom" 
                        name="use_custom" 
                        value="1"
                        @if(old('use_custom', $isCustom)) checked @endif
                        class="w-5 h-5 text-primary border-gray-300 rounded"
                    >
                    <label for="use_custom" class="text-sm font-medium text-gray-700 cursor-pointer">
                        Use Custom Template
                    </label>
                    <p class="text-xs text-gray-500 ml-2">
                        When enabled, this custom template will be used instead of the default Blade file
                    </p>
                </div>

                <!-- Available Variables Info -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="text-sm font-semibold text-blue-900 mb-2">
                        <i class="fas fa-info-circle mr-1"></i> Available Variables
                    </h4>
                    <div class="text-xs text-blue-800">
                        <p class="mb-1"><strong>Common:</strong> {{ '$appName' }}, {{ '$business->name' }}, {{ '$business->email' }}</p>
                        <p id="template-specific-vars" class="mb-0"></p>
                    </div>
                </div>

                <!-- Template Content -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="content" class="block text-sm font-medium text-gray-700">
                            Email Template Content (Blade/HTML) <span class="text-red-500">*</span>
                        </label>
                        <div class="flex items-center space-x-2">
                            <button 
                                type="button" 
                                id="toggle-view-btn"
                                class="px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors"
                                onclick="toggleView()"
                            >
                                <i class="fas fa-code mr-1" id="toggle-icon"></i>
                                <span id="toggle-text">Preview</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- HTML Editor View -->
                    <div id="html-editor-view">
                        <textarea 
                            id="content" 
                            name="content" 
                            rows="25"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent font-mono text-sm"
                            required
                            oninput="updatePreview()"
                        >{{ old('content', $customContent) }}</textarea>
                        <p class="text-xs text-gray-500 mt-1">
                            Use Blade syntax and HTML. Include full HTML structure with DOCTYPE, html, head, and body tags.
                        </p>
                    </div>
                    
                    <!-- Preview View -->
                    <div id="preview-view" class="hidden border border-gray-300 rounded-lg bg-white">
                        <div class="bg-gray-50 border-b border-gray-200 px-4 py-2 flex items-center justify-between">
                            <span class="text-xs font-medium text-gray-700">Email Preview</span>
                            <span class="text-xs text-gray-500">Note: Blade variables will show as placeholders</span>
                        </div>
                        <div class="p-4 overflow-auto" style="max-height: 600px;">
                            <iframe 
                                id="preview-frame" 
                                class="w-full border-0"
                                style="min-height: 500px;"
                                sandbox="allow-same-origin"
                            ></iframe>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200">
                    <a href="{{ route('admin.email-templates.index') }}" 
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90">
                        Save Template
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Help Section -->
    <div class="mt-6 bg-gray-50 border border-gray-200 rounded-lg p-6">
        <h4 class="text-md font-semibold text-gray-900 mb-3">Template Guidelines</h4>
        <div class="text-sm text-gray-700 space-y-2">
            <p>• Include full HTML structure: DOCTYPE, html, head, and body tags</p>
            <p>• Use inline CSS for email compatibility</p>
            <p>• Access variables using Blade syntax: @{{ $variableName }}</p>
            <p>• Use the logo from settings: @{{ asset('storage/' . \App\Models\Setting::get('site_logo')) }}</p>
        </div>
    </div>
</div>

<script>
(function() {
    var template = '{{ $template }}';
    var varsMap = {
        'business-verification': '<strong>Template-specific:</strong> {{ '$verificationUrl' }}',
        'login-notification': '<strong>Template-specific:</strong> {{ '$ipAddress' }}, {{ '$userAgent' }}',
        'new-deposit': '<strong>Template-specific:</strong> {{ '$payment->amount' }}, {{ '$payment->reference' }}, {{ '$payment->created_at' }}',
        'website-approved': '<strong>Template-specific:</strong> {{ '$website->website_url' }}',
        'website-added': '<strong>Template-specific:</strong> {{ '$website->website_url' }}',
        'withdrawal-requested': '<strong>Template-specific:</strong> {{ '$withdrawal->amount' }}, {{ '$withdrawal->bank_name' }}, {{ '$withdrawal->account_name' }}, {{ '$withdrawal->account_number' }}, {{ '$withdrawal->created_at' }}',
        'withdrawal-approved': '<strong>Template-specific:</strong> {{ '$withdrawal->amount' }}, {{ '$withdrawal->bank_name' }}, {{ '$withdrawal->account_name' }}, {{ '$withdrawal->account_number' }}, {{ '$withdrawal->created_at' }}',
        'password-changed': '<strong>Template-specific:</strong> {{ '$ipAddress' }}, {{ '$userAgent' }}'
    };
    
    var varsElement = document.getElementById('template-specific-vars');
    if (varsMap[template]) {
        varsElement.innerHTML = varsMap[template];
    } else {
        varsElement.innerHTML = '<strong>Template-specific:</strong> None';
    }
    
    // Initialize preview
    updatePreview();
})();

var isPreviewMode = false;

function toggleView() {
    var htmlView = document.getElementById('html-editor-view');
    var previewView = document.getElementById('preview-view');
    var toggleBtn = document.getElementById('toggle-view-btn');
    var toggleIcon = document.getElementById('toggle-icon');
    var toggleText = document.getElementById('toggle-text');
    
    isPreviewMode = !isPreviewMode;
    
    if (isPreviewMode) {
        htmlView.classList.add('hidden');
        previewView.classList.remove('hidden');
        toggleIcon.className = 'fas fa-edit mr-1';
        toggleText.textContent = 'Edit HTML';
        updatePreview();
    } else {
        htmlView.classList.remove('hidden');
        previewView.classList.add('hidden');
        toggleIcon.className = 'fas fa-code mr-1';
        toggleText.textContent = 'Preview';
    }
}

function updatePreview() {
    var content = document.getElementById('content').value;
    var previewFrame = document.getElementById('preview-frame');
    
    if (!previewFrame) return;
    
    // Replace Blade syntax with sample data for preview
    var previewContent = content
        .replace(/\{\{\s*\$appName\s*\}\}/g, 'CheckoutPay')
        .replace(/\{\{\s*\$business->name\s*\}\}/g, 'Sample Business Name')
        .replace(/\{\{\s*\$business->email\s*\}\}/g, 'business@example.com')
        .replace(/\{\{\s*\$verificationUrl\s*\}\}/g, '#')
        .replace(/\{\{\s*\$ipAddress\s*\}\}/g, '192.168.1.1')
        .replace(/\{\{\s*\$userAgent\s*\}\}/g, 'Mozilla/5.0 (Sample Browser)')
        .replace(/\{\{\s*\$payment->amount\s*\}\}/g, '₦10,000.00')
        .replace(/\{\{\s*\$payment->reference\s*\}\}/g, 'REF123456')
        .replace(/\{\{\s*\$payment->created_at\s*\}\}/g, new Date().toLocaleString())
        .replace(/\{\{\s*\$website->website_url\s*\}\}/g, 'https://example.com')
        .replace(/\{\{\s*\$withdrawal->amount\s*\}\}/g, '₦5,000.00')
        .replace(/\{\{\s*\$withdrawal->bank_name\s*\}\}/g, 'Sample Bank')
        .replace(/\{\{\s*\$withdrawal->account_name\s*\}\}/g, 'John Doe')
        .replace(/\{\{\s*\$withdrawal->account_number\s*\}\}/g, '1234567890')
        .replace(/\{\{\s*\$withdrawal->created_at\s*\}\}/g, new Date().toLocaleString())
        .replace(/\{\{\s*asset\([^)]+\)\s*\}\}/g, '/storage/logo.png')
        .replace(/\{\{\s*date\('Y'\)\s*\}\}/g, new Date().getFullYear())
        .replace(/\{\{\s*now\(\)->format\([^)]+\)\s*\}\}/g, new Date().toLocaleString());
    
    // Write to iframe
    var iframeDoc = previewFrame.contentDocument || previewFrame.contentWindow.document;
    iframeDoc.open();
    iframeDoc.write(previewContent);
    iframeDoc.close();
}
</script>
@endsection

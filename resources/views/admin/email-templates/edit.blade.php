@extends('layouts.admin')

@section('title', 'Edit Email Template')
@section('page-title', 'Edit Email Template: ' . $templateInfo['name'])

@section('content')
<div class="max-w-6xl">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form action="{{ route('admin.email-templates.update', $template) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="space-y-6">
                <!-- Template Info -->
                <div class="border-b border-gray-200 pb-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ $templateInfo['name'] }}</h3>
                    <p class="text-sm text-gray-600">{{ $templateInfo['description'] }}</p>
                </div>

                <!-- Subject -->
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
                        placeholder="Email subject line"
                    >
                    <p class="text-xs text-gray-500 mt-1">You can use variables like @{{ $appName }} in the subject</p>
                </div>

                <!-- Use Custom Toggle -->
                <div class="flex items-center space-x-3 p-4 bg-gray-50 rounded-lg">
                    <input 
                        type="checkbox" 
                        id="use_custom" 
                        name="use_custom" 
                        value="1"
                        {{ old('use_custom', $isCustom) ? 'checked' : '' }}
                        class="w-5 h-5 text-primary border-gray-300 rounded focus:ring-primary focus:ring-2"
                    >
                    <label for="use_custom" class="text-sm font-medium text-gray-700 cursor-pointer">
                        Use Custom Template
                    </label>
                    <p class="text-xs text-gray-500 ml-2">
                        When enabled, this custom template will be used instead of the default Blade file
                    </p>
                </div>

                <!-- Available Variables -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="text-sm font-semibold text-blue-900 mb-3">
                        <i class="fas fa-code mr-1"></i> Available Variables
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        @php
                            $vars = $availableVariables ?? [];
                        @endphp
                        @forelse($vars as $var => $description)
                        <div class="text-xs">
                            <code class="bg-blue-100 text-blue-800 px-2 py-1 rounded font-mono">{{ $var }}</code>
                            <span class="text-blue-700 ml-2">{{ $description }}</span>
                        </div>
                        @empty
                        <div class="text-xs text-gray-500">No variables available for this template.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Template Content Editor -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="content" class="block text-sm font-medium text-gray-700">
                            Email Template Content (Blade/HTML) <span class="text-red-500">*</span>
                        </label>
                        <div class="text-xs text-gray-500">
                            <i class="fas fa-code mr-1"></i> Supports Blade syntax and HTML
                        </div>
                    </div>
                    <textarea 
                        id="content" 
                        name="content" 
                        rows="30"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent font-mono text-sm"
                        required
                        placeholder="Enter your email template HTML/Blade code here..."
                    >{{ old('content', $customContent) }}</textarea>
                    <p class="text-xs text-gray-500 mt-1">
                        Use Blade syntax (@{{ }}, @if, @forelse, etc.) and HTML/CSS for styling.
                        The template should include full HTML structure with &lt;!DOCTYPE html&gt;.
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200">
                    <a href="{{ route('admin.email-templates.index') }}" 
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-arrow-left mr-2"></i> Back to List
                    </a>
                    <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90">
                        <i class="fas fa-save mr-2"></i> Save Template
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Help Section -->
    <div class="mt-6 bg-gray-50 border border-gray-200 rounded-lg p-6">
        <h4 class="text-md font-semibold text-gray-900 mb-3">
            <i class="fas fa-question-circle mr-2 text-primary"></i> Template Guidelines
        </h4>
        <ul class="space-y-2 text-sm text-gray-700">
            <li class="flex items-start">
                <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                <span>Include full HTML structure: <code class="bg-gray-200 px-1 rounded">&lt;!DOCTYPE html&gt;</code>, <code class="bg-gray-200 px-1 rounded">&lt;html&gt;</code>, <code class="bg-gray-200 px-1 rounded">&lt;head&gt;</code>, <code class="bg-gray-200 px-1 rounded">&lt;body&gt;</code></span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                <span>Use inline CSS for email compatibility (most email clients don't support external stylesheets)</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                <span>Access variables using Blade syntax: <code class="bg-gray-200 px-1 rounded">@{{ $variableName }}</code></span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                <span>Use the logo from settings: <code class="bg-gray-200 px-1 rounded">@{{ asset('storage/' . \App\Models\Setting::get('site_logo')) }}</code></span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                <span>Test your template before saving to ensure all variables are properly displayed</span>
            </li>
        </ul>
    </div>
</div>
@endsection

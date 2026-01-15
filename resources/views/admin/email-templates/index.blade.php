@extends('layouts.admin')

@section('title', 'Email Templates')
@section('page-title', 'Email Templates')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Manage Email Templates</h3>
            <p class="text-sm text-gray-600 mt-1">Customize email templates sent to businesses</p>
        </div>
    </div>

    <!-- Templates Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($customTemplates as $key => $template)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h4 class="text-md font-semibold text-gray-900">{{ $template['name'] }}</h4>
                    <p class="text-xs text-gray-500 mt-1">{{ $template['description'] }}</p>
                </div>
                @if($template['has_custom'])
                    <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">Custom</span>
                @else
                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Default</span>
                @endif
            </div>
            
            <div class="mb-4">
                <p class="text-xs text-gray-500 mb-1">Subject:</p>
                <p class="text-sm text-gray-700 font-medium">{{ $template['subject'] }}</p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.email-templates.edit', $key) }}" 
                    class="flex-1 bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 text-center text-sm">
                    <i class="fas fa-edit mr-1"></i> Edit
                </a>
                @if($template['has_custom'])
                <form action="{{ route('admin.email-templates.reset', $key) }}" method="POST" class="inline">
                    @csrf
                    @method('POST')
                    <button type="submit" 
                        class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 text-sm"
                        onclick="return confirm('Are you sure you want to reset this template to default?');">
                        <i class="fas fa-undo mr-1"></i> Reset
                    </button>
                </form>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    <!-- Info Box -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
            <div class="text-sm text-blue-800">
                <p class="font-semibold mb-1">About Email Templates</p>
                <p class="text-blue-700">
                    You can customize email templates using Blade syntax. Available variables will be shown in the editor.
                    Templates support HTML and CSS styling. Changes take effect immediately after saving.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

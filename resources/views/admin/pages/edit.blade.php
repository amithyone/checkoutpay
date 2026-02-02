@extends('layouts.admin')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('title', 'Edit Page')
@section('page-title', 'Edit Page')

@section('content')
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <form action="{{ route('admin.pages.update', $page) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Slug <span class="text-red-500">*</span></label>
                <input type="text" name="slug" value="{{ old('slug', $page->slug) }}" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title', $page->title) }}" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Content</label>
                @php
                    $contentValue = old('content', $page->content);
                    if (is_array($contentValue)) {
                        $contentValue = json_encode($contentValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    } elseif (is_string($contentValue)) {
                        // Keep as is for HTML content
                    }
                @endphp
                <textarea name="content" rows="20" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary font-mono text-sm">{{ $contentValue }}</textarea>
                <p class="mt-1 text-xs text-gray-500">
                    @if($page->slug === 'products-invoices')
                        <strong>HTML Content:</strong> This page supports full HTML editing. You can use Tailwind CSS classes (already loaded) and Font Awesome icons. The content will be rendered between the navigation and footer.
                    @elseif(in_array($page->slug, ['home', 'pricing']))
                        Content should be valid JSON for structured data.
                    @else
                        HTML content is supported. Use HTML tags and Tailwind CSS classes.
                    @endif
                </p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Meta Title</label>
                    <input type="text" name="meta_title" value="{{ old('meta_title', $page->meta_title) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Order</label>
                    <input type="number" name="order" value="{{ old('order', $page->order) }}" min="0"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Meta Description</label>
                <textarea name="meta_description" rows="2"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">{{ old('meta_description', $page->meta_description) }}</textarea>
            </div>

            <div>
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="is_published" value="1" {{ old('is_published', $page->is_published) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-primary focus:ring-primary">
                    <span class="text-sm text-gray-700">Published</span>
                </label>
            </div>

            <div class="flex justify-end space-x-3">
                <a href="{{ route('admin.pages.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    Update Page
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

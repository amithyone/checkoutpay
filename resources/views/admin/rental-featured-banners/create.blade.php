@extends('layouts.admin')

@section('title', 'Add featured banner')

@section('content')
<div class="p-4 max-w-3xl">
    <div class="mb-4">
        <a href="{{ route('admin.rental-featured-banners.index') }}" class="text-xs text-gray-500 hover:text-primary">&larr; Back to banners</a>
        <h1 class="text-lg font-bold text-gray-900 mt-1">Add featured banner</h1>
    </div>

    <form method="POST" action="{{ route('admin.rental-featured-banners.store') }}" enctype="multipart/form-data" class="bg-white rounded-lg border border-gray-100 shadow-sm p-4 space-y-4">
        @csrf

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-0.5">Title *</label>
            <input type="text" name="title" value="{{ old('title') }}" required maxlength="255" class="w-full text-sm border-gray-300 rounded-md">
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-0.5">Subtitle</label>
            <input type="text" name="subtitle" value="{{ old('subtitle') }}" maxlength="500" class="w-full text-sm border-gray-300 rounded-md">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-0.5">Pill tag</label>
                <input type="text" name="tag" value="{{ old('tag', 'Sponsored') }}" maxlength="120" class="w-full text-sm border-gray-300 rounded-md">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-0.5">Sort order</label>
                <input type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', 1) }}" class="w-full text-sm border-gray-300 rounded-md">
                <p class="text-[10px] text-gray-500 mt-0.5">Lower numbers appear first in the slider.</p>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-0.5">Banner image *</label>
            <input type="file" name="image" accept="image/*" required class="w-full text-sm">
            <p class="text-[10px] text-gray-500 mt-0.5">Recommended wide image for mobile carousel.</p>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-0.5">External link URL</label>
            <input type="url" name="link_url" value="{{ old('link_url') }}" placeholder="https://..." class="w-full text-sm border-gray-300 rounded-md">
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-0.5">Or link to rental item</label>
            <select name="rental_item_id" class="w-full text-sm border-gray-300 rounded-md">
                <option value="">— None —</option>
                @foreach($items as $item)
                    <option value="{{ $item->id }}" @selected(old('rental_item_id') == $item->id)>{{ $item->name }} ({{ $item->slug }})</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-0.5">Starts at</label>
                <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="w-full text-sm border-gray-300 rounded-md">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-0.5">Ends at</label>
                <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="w-full text-sm border-gray-300 rounded-md">
            </div>
        </div>

        <label class="inline-flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="rounded">
            <span>Active</span>
        </label>

        <button type="submit" class="w-full text-sm bg-primary text-white py-2 rounded-md hover:bg-primary/90 font-medium">Create banner</button>
    </form>
</div>
@endsection

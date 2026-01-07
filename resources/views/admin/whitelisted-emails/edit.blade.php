@extends('layouts.admin')

@section('title', 'Edit Whitelisted Email')
@section('page-title', 'Edit Whitelisted Email')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form action="{{ route('admin.whitelisted-emails.update', $whitelistedEmail) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                    Email Address or Domain <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    id="email" 
                    name="email" 
                    value="{{ old('email', $whitelistedEmail->email) }}"
                    placeholder="alerts@gtbank.com or @gtbank.com"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent @error('email') border-red-500 @enderror"
                    required
                >
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-2 text-sm text-gray-500">
                    <strong>Examples:</strong>
                </p>
                <ul class="mt-1 text-sm text-gray-500 list-disc list-inside">
                    <li>Specific email: <code class="bg-gray-100 px-1 rounded">alerts@gtbank.com</code></li>
                    <li>Domain (all emails): <code class="bg-gray-100 px-1 rounded">@gtbank.com</code></li>
                    <li>Partial match: <code class="bg-gray-100 px-1 rounded">gtbank</code> (matches any email containing "gtbank")</li>
                </ul>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                    Description (Optional)
                </label>
                <textarea 
                    id="description" 
                    name="description" 
                    rows="3"
                    placeholder="e.g., GTBank transaction notifications"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                >{{ old('description', $whitelistedEmail->description) }}</textarea>
            </div>

            <div class="flex items-center">
                <input 
                    type="checkbox" 
                    id="is_active" 
                    name="is_active" 
                    value="1"
                    {{ old('is_active', $whitelistedEmail->is_active) ? 'checked' : '' }}
                    class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
                >
                <label for="is_active" class="ml-2 block text-sm text-gray-700">
                    Active (emails from this address will be accepted)
                </label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                <a href="{{ route('admin.whitelisted-emails.index') }}" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">
                    Cancel
                </a>
                <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90 flex items-center">
                    <i class="fas fa-save mr-2"></i> Update Whitelisted Email
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

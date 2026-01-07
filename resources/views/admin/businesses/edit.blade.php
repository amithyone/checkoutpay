@extends('layouts.admin')

@section('title', 'Edit Business')
@section('page-title', 'Edit Business')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form action="{{ route('admin.businesses.update', $business) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Business Name *</label>
                    <input type="text" name="name" id="name" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('name', $business->name) }}">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" name="email" id="email" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('email', $business->email) }}">
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" id="phone"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('phone', $business->phone) }}">
                </div>

                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea name="address" id="address" rows="3"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary">{{ old('address', $business->address) }}</textarea>
                </div>

                <div>
                    <label for="webhook_url" class="block text-sm font-medium text-gray-700 mb-1">Webhook URL</label>
                    <input type="url" name="webhook_url" id="webhook_url"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('webhook_url', $business->webhook_url) }}">
                </div>

                <div>
                    <label for="email_account_id" class="block text-sm font-medium text-gray-700 mb-1">Email Account</label>
                    <select name="email_account_id" id="email_account_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary">
                        <option value="">-- Select Email Account --</option>
                        @foreach($emailAccounts as $emailAccount)
                            <option value="{{ $emailAccount->id }}" {{ old('email_account_id', $business->email_account_id) == $emailAccount->id ? 'selected' : '' }}>
                                {{ $emailAccount->name }} ({{ $emailAccount->email }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Select which email account to monitor for this business's payments</p>
                </div>

                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $business->is_active) ? 'checked' : '' }} class="mr-2">
                        <span class="text-sm text-gray-700">Active</span>
                    </label>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                    <a href="{{ route('admin.businesses.show', $business) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                        Update Business
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

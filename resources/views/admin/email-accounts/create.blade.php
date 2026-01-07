@extends('layouts.admin')

@section('title', 'Create Email Account')
@section('page-title', 'Create Email Account')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form action="{{ route('admin.email-accounts.store') }}" method="POST">
            @csrf
            <div class="space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Account Name *</label>
                    <input type="text" name="name" id="name" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('name') }}" placeholder="e.g., Main Gmail Account">
                    <p class="text-xs text-gray-500 mt-1">A friendly name to identify this email account</p>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                    <input type="email" name="email" id="email" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('email') }}" placeholder="your-email@gmail.com">
                </div>

                <div>
                    <label for="host" class="block text-sm font-medium text-gray-700 mb-1">IMAP Host *</label>
                    <input type="text" name="host" id="host" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('host', 'imap.gmail.com') }}" placeholder="imap.gmail.com">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="port" class="block text-sm font-medium text-gray-700 mb-1">Port *</label>
                        <input type="number" name="port" id="port" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                            value="{{ old('port', 993) }}" min="1" max="65535">
                    </div>
                    <div>
                        <label for="encryption" class="block text-sm font-medium text-gray-700 mb-1">Encryption *</label>
                        <select name="encryption" id="encryption" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary">
                            <option value="ssl" {{ old('encryption', 'ssl') === 'ssl' ? 'selected' : '' }}>SSL</option>
                            <option value="tls" {{ old('encryption') === 'tls' ? 'selected' : '' }}>TLS</option>
                            <option value="none" {{ old('encryption') === 'none' ? 'selected' : '' }}>None</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password/App Password *</label>
                    <input type="password" name="password" id="password" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        placeholder="Enter email password or app password">
                    <p class="text-xs text-gray-500 mt-1">For Gmail, use an App Password (not your regular password)</p>
                </div>

                <div>
                    <label for="folder" class="block text-sm font-medium text-gray-700 mb-1">Folder</label>
                    <input type="text" name="folder" id="folder"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('folder', 'INBOX') }}" placeholder="INBOX">
                    <p class="text-xs text-gray-500 mt-1">IMAP folder to monitor (default: INBOX)</p>
                </div>

                <div class="flex items-center space-x-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="validate_cert" value="1" {{ old('validate_cert') ? 'checked' : '' }} class="mr-2">
                        <span class="text-sm text-gray-700">Validate SSL Certificate</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="mr-2">
                        <span class="text-sm text-gray-700">Active</span>
                    </label>
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" id="notes" rows="3"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        placeholder="Additional notes about this email account">{{ old('notes') }}</textarea>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                    <a href="{{ route('admin.email-accounts.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                        Create Email Account
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

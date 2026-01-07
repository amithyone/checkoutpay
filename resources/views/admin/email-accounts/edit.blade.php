@extends('layouts.admin')

@section('title', 'Edit Email Account')
@section('page-title', 'Edit Email Account')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form action="{{ route('admin.email-accounts.update', $emailAccount) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Account Name *</label>
                    <input type="text" name="name" id="name" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('name', $emailAccount->name) }}">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                    <input type="email" name="email" id="email" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('email', $emailAccount->email) }}">
                </div>

                <div>
                    <label for="host" class="block text-sm font-medium text-gray-700 mb-1">IMAP Host *</label>
                    <input type="text" name="host" id="host" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('host', $emailAccount->host) }}">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="port" class="block text-sm font-medium text-gray-700 mb-1">Port *</label>
                        <input type="number" name="port" id="port" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                            value="{{ old('port', $emailAccount->port) }}" min="1" max="65535">
                    </div>
                    <div>
                        <label for="encryption" class="block text-sm font-medium text-gray-700 mb-1">Encryption *</label>
                        <select name="encryption" id="encryption" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                            onchange="updateEncryptionHint()">
                            <option value="ssl" {{ old('encryption', $emailAccount->encryption) === 'ssl' ? 'selected' : '' }}>SSL (Port 993)</option>
                            <option value="tls" {{ old('encryption', $emailAccount->encryption) === 'tls' ? 'selected' : '' }}>TLS (Port 587/143)</option>
                            <option value="none" {{ old('encryption', $emailAccount->encryption) === 'none' ? 'selected' : '' }}>None</option>
                        </select>
                        <p id="encryption-hint" class="text-xs text-gray-500 mt-1">
                            For Gmail with port 993, use SSL. For port 587, use TLS.
                        </p>
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password/App Password</label>
                    <input type="password" name="password" id="password"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        placeholder="Leave blank to keep current password">
                    <p class="text-xs text-gray-500 mt-1">
                        Leave blank to keep current password. For Gmail: Use App Password from 
                        <a href="https://myaccount.google.com/apppasswords" target="_blank" class="text-blue-600 hover:underline">Google App Passwords</a>
                    </p>
                </div>

                <div>
                    <label for="folder" class="block text-sm font-medium text-gray-700 mb-1">Folder</label>
                    <input type="text" name="folder" id="folder"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        value="{{ old('folder', $emailAccount->folder) }}">
                </div>

                <div class="flex items-center space-x-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="validate_cert" value="1" {{ old('validate_cert', $emailAccount->validate_cert) ? 'checked' : '' }} class="mr-2">
                        <span class="text-sm text-gray-700">Validate SSL Certificate</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $emailAccount->is_active) ? 'checked' : '' }} class="mr-2">
                        <span class="text-sm text-gray-700">Active</span>
                    </label>
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" id="notes" rows="3"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary">{{ old('notes', $emailAccount->notes) }}</textarea>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="testConnection({{ $emailAccount->id }})" 
                        class="px-4 py-2 border border-blue-300 rounded-lg text-blue-700 hover:bg-blue-50">
                        Test Connection
                    </button>
                    <a href="{{ route('admin.email-accounts.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                        Update Email Account
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function testConnection(id) {
    const btn = event.target;
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Testing...';
    
    fetch(`/admin/email-accounts/${id}/test-connection`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Connection successful!');
        } else {
            alert('❌ Connection failed: ' + data.message);
        }
    })
    .catch(error => {
        alert('❌ Error testing connection: ' + error.message);
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = originalText;
    });
}
</script>
@endsection

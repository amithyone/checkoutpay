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
                    <label for="method" class="block text-sm font-medium text-gray-700 mb-1">Connection Method *</label>
                    <select name="method" id="method" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                        onchange="toggleMethodFields()">
                        <option value="imap" {{ old('method', 'imap') === 'imap' ? 'selected' : '' }}>IMAP (Traditional)</option>
                        <option value="gmail_api" {{ old('method') === 'gmail_api' ? 'selected' : '' }}>Gmail API (Recommended - Bypasses Firewall)</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        <strong>Gmail API:</strong> Uses HTTPS (port 443), never blocked by firewalls. 
                        <a href="{{ url('GMAIL_API_SETUP.md') }}" target="_blank" class="text-blue-600 hover:underline">Setup Guide</a>
                    </p>
                </div>

                <!-- IMAP Fields -->
                <div id="imap-fields">
                    <div>
                        <label for="host" class="block text-sm font-medium text-gray-700 mb-1">IMAP Host *</label>
                        <input type="text" name="host" id="host"
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
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                            onchange="updateEncryptionHint()">
                            <option value="ssl" {{ old('encryption', 'ssl') === 'ssl' ? 'selected' : '' }}>SSL (Port 993)</option>
                            <option value="tls" {{ old('encryption') === 'tls' ? 'selected' : '' }}>TLS (Port 587/143)</option>
                            <option value="none" {{ old('encryption') === 'none' ? 'selected' : '' }}>None</option>
                        </select>
                        <p id="encryption-hint" class="text-xs text-gray-500 mt-1">
                            For Gmail with port 993, use SSL. For port 587, use TLS.
                        </p>
                    </div>
                </div>

                <!-- IMAP Password -->
                <div id="imap-password">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password/App Password *</label>
                        <input type="password" name="password" id="password"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                            placeholder="Enter email password or app password">
                        <p class="text-xs text-gray-500 mt-1">
                            For Gmail: Go to <a href="https://myaccount.google.com/apppasswords" target="_blank" class="text-blue-600 hover:underline">Google App Passwords</a>, 
                            create a new app password with any name, and paste the 16-character code here.
                        </p>
                    </div>
                </div>

                <!-- Gmail API Fields -->
                <div id="gmail-api-fields" style="display: none;">
                    <div>
                        <label for="gmail_credentials_path" class="block text-sm font-medium text-gray-700 mb-1">Credentials File Path *</label>
                        <input type="text" name="gmail_credentials_path" id="gmail_credentials_path"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary"
                            value="{{ old('gmail_credentials_path', 'gmail-credentials.json') }}" placeholder="gmail-credentials.json">
                        <p class="text-xs text-gray-500 mt-1">
                            Path to credentials JSON file (relative to storage/app/). 
                            Upload your credentials file from Google Cloud Console.
                        </p>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
                        <p class="text-sm text-blue-800">
                            <strong>📋 Setup Required:</strong> After creating this account, you'll need to:
                        </p>
                        <ol class="list-decimal list-inside text-sm text-blue-700 mt-2 space-y-1">
                            <li>Upload credentials JSON to <code class="bg-blue-100 px-1 rounded">storage/app/</code></li>
                            <li>Update redirect URI in Google Cloud Console</li>
                            <li>Click "Authorize Gmail" button to complete setup</li>
                        </ol>
                        <a href="{{ url('GMAIL_API_SETUP.md') }}" target="_blank" class="text-blue-600 hover:underline text-sm mt-2 inline-block">
                            View detailed setup guide →
                        </a>
                    </div>
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
                    <label for="allowed_senders" class="block text-sm font-medium text-gray-700 mb-1">Allowed Senders (Optional)</label>
                    <textarea name="allowed_senders" id="allowed_senders" rows="4"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary font-mono text-sm"
                        placeholder="alerts@gtbank.com&#10;notifications@accessbank.com&#10;@zenithbank.com&#10;transactions@uba.com">{{ old('allowed_senders', is_array(old('allowed_senders')) ? implode("\n", old('allowed_senders')) : '') }}</textarea>
                    <p class="text-xs text-gray-500 mt-1">
                        <strong>Filter emails by sender:</strong> Enter one email address or domain per line. 
                        Only emails from these senders will be processed. Leave empty to process all emails.
                        <br>
                        <strong>Examples:</strong>
                        <br>• <code>alerts@gtbank.com</code> - Exact email match
                        <br>• <code>@gtbank.com</code> - All emails from gtbank.com domain
                        <br>• <code>notifications@accessbank.com</code> - Specific sender
                    </p>
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

<script>
function toggleMethodFields() {
    const method = document.getElementById('method').value;
    const imapFields = document.getElementById('imap-fields');
    const imapPassword = document.getElementById('imap-password');
    const gmailApiFields = document.getElementById('gmail-api-fields');
    const hostInput = document.getElementById('host');
    const passwordInput = document.getElementById('password');
    
    if (method === 'gmail_api') {
        imapFields.style.display = 'none';
        imapPassword.style.display = 'none';
        gmailApiFields.style.display = 'block';
        hostInput.removeAttribute('required');
        passwordInput.removeAttribute('required');
    } else {
        imapFields.style.display = 'block';
        imapPassword.style.display = 'block';
        gmailApiFields.style.display = 'none';
        hostInput.setAttribute('required', 'required');
        passwordInput.setAttribute('required', 'required');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', toggleMethodFields);
</script>
@endsection

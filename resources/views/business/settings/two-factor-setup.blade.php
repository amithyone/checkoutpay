@extends('layouts.business')

@section('title', 'Set Up Two-Factor Authentication')
@section('page-title', 'Set Up Two-Factor Authentication')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-shield-alt text-primary text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Set Up Two-Factor Authentication</h2>
            <p class="text-gray-600">Scan the QR code with your authenticator app to enable 2FA</p>
        </div>

        <div class="space-y-6">
            <!-- QR Code -->
            <div class="bg-gray-50 rounded-lg p-6 text-center">
                <p class="text-sm text-gray-600 mb-4">Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)</p>
                <div class="inline-block bg-white p-4 rounded-lg border-2 border-gray-200">
                    {!! QrCode::size(200)->margin(1)->generate($qrCodeUrl) !!}
                </div>
                <p class="text-xs text-gray-500 mt-4">Or enter this code manually: <code class="bg-gray-200 px-2 py-1 rounded font-mono break-all">{{ $business->two_factor_secret }}</code></p>
            </div>

            <!-- Verification Form -->
            <form method="POST" action="{{ route('business.settings.2fa.verify') }}">
                @csrf
                
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700 mb-2">
                        Enter Verification Code
                    </label>
                    <input type="text" name="code" id="code" maxlength="6" pattern="[0-9]{6}" required
                        placeholder="000000"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-center text-2xl font-mono tracking-widest"
                        autocomplete="off">
                    <p class="mt-2 text-xs text-gray-500">Enter the 6-digit code from your authenticator app</p>
                    @error('code')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-6 flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-3 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium">
                        <i class="fas fa-check mr-2"></i> Verify and Enable 2FA
                    </button>
                    <a href="{{ route('business.settings.index') }}" class="px-4 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium">
                        Cancel
                    </a>
                </div>
            </form>

            <!-- Instructions -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-blue-900 mb-2">
                    <i class="fas fa-info-circle mr-2"></i> How to set up:
                </h4>
                <ol class="text-sm text-blue-800 space-y-1 list-decimal list-inside">
                    <li>Install an authenticator app on your phone (Google Authenticator, Authy, Microsoft Authenticator)</li>
                    <li>Open the app and scan the QR code above</li>
                    <li>Enter the 6-digit code shown in the app</li>
                    <li>Click "Verify and Enable 2FA"</li>
                </ol>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('code').addEventListener('input', function(e) {
    // Only allow numbers
    this.value = this.value.replace(/[^0-9]/g, '');
    
    // Auto-submit when 6 digits are entered
    if (this.value.length === 6) {
        // Optional: auto-submit after a short delay
        // setTimeout(() => this.form.submit(), 500);
    }
});
</script>
@endpush
@endsection

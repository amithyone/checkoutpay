@extends('layouts.business')

@section('title', 'Pay at shop')
@section('page-title', 'Pay at shop')

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

    <div class="bg-gradient-to-r from-sky-50 to-indigo-50 border border-sky-200 rounded-xl p-5 lg:p-6">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 bg-sky-100 rounded-xl flex items-center justify-center shrink-0">
                <i class="fas fa-broadcast-tower text-sky-600 text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-semibold text-gray-900">CheckoutNow Pay at shop</h2>
                <p class="text-sm text-gray-600 mt-1 max-w-2xl">
                    Let customers pay in your shop by opening CheckoutNow near your POS. Your terminal broadcasts a signed Bluetooth payment request;
                    CheckoutNow verifies it with CheckoutPay and pre-fills the transfer to your settlement account.
                </p>
            </div>
        </div>
    </div>

    @if(! $business->broadcast_pay_at_shop_enabled)
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-5">
            <h3 class="text-sm font-semibold text-amber-900">Not available on your account yet</h3>
            <p class="text-sm text-amber-800 mt-1">
                Pay at shop must be enabled by CheckoutPay for your business. Complete verification and contact support if you need in-store CheckoutNow payments.
            </p>
            <a href="{{ route('business.verification.index') }}" class="inline-flex items-center mt-3 text-sm font-medium text-amber-900 underline">
                Go to Verification
            </a>
        </div>
    @elseif(! $settlement)
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-5">
            <h3 class="text-sm font-semibold text-amber-900">Settlement account required</h3>
            <p class="text-sm text-amber-800 mt-1">
                You need an active CheckoutPay account number before Pay at shop can receive customer transfers.
            </p>
            <a href="{{ route('business.keys.index') }}" class="inline-flex items-center mt-3 text-sm font-medium text-amber-900 underline">
                API Keys &amp; account number
            </a>
        </div>
    @else
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Status</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        @if($business->broadcast_pay_at_shop_active && $terminal && $terminal->active)
                            Your POS credentials are active. CheckoutNow can verify payments to
                            <span class="font-medium">{{ $settlement['masked_account_suffix'] }}</span>
                            ({{ $settlement['bank_name'] }}).
                        @else
                            Turn on Pay at shop when your POS is configured with the credentials below.
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    @if($business->broadcast_pay_at_shop_active && $terminal && $terminal->active)
                        <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                    @else
                        <span class="px-3 py-1 text-sm font-medium bg-gray-100 text-gray-700 rounded-full">Off</span>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('business.pay-at-shop.toggle') }}" class="mt-6 pt-6 border-t border-gray-100">
                @csrf
                @if($business->broadcast_pay_at_shop_active && $terminal && $terminal->active)
                    <input type="hidden" name="enable" value="0">
                    <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-800 rounded-lg hover:bg-gray-200 text-sm"
                        onclick="return confirm('Turn off Pay at shop? POS broadcasts will stop verifying until you turn it back on.')">
                        <i class="fas fa-power-off mr-2"></i> Turn off Pay at shop
                    </button>
                @else
                    <input type="hidden" name="enable" value="1">
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm">
                        <i class="fas fa-power-off mr-2"></i> Turn on Pay at shop
                    </button>
                @endif
            </form>
        </div>

        @if($terminal)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">POS credentials</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Enter these in your Checkout Broadcast–compatible POS app. Keep the signing key secret — it proves broadcasts came from your terminal.
                </p>

                <div class="mb-6 bg-sky-50 border border-sky-200 rounded-lg p-4 text-sm text-sky-900">
                    <p class="font-semibold mb-2"><i class="fas fa-info-circle mr-2"></i>How Pay at shop works</p>
                    <ol class="list-decimal list-inside space-y-1.5 text-sky-800 mb-4">
                        <li><strong>POS broadcasts</strong> a signed Bluetooth packet: amount, terminal ID, bank name hash, masked suffix only — <em>never</em> the full account number.</li>
                        <li><strong>Customer phone</strong> scans the broadcast and calls verify on CheckoutPay.</li>
                        <li><strong>Server returns</strong> merchant name, full settlement account, bank code — the app pre-fills the transfer.</li>
                        <li><strong>Customer pays</strong> in CheckoutNow; POS polls session status until <code class="bg-white/80 px-1 rounded">paid</code>.</li>
                    </ol>
                    <p class="font-semibold mb-2">POS setup checklist</p>
                    <ol class="list-decimal list-inside space-y-1.5 text-sky-800">
                        <li>Set <strong>Terminal ID</strong>, <strong>API key</strong>, and <strong>Signing key</strong> below in your POS app.</li>
                        <li>Set <strong>Signature algorithm</strong> to <code class="bg-white/80 px-1 rounded">ed25519</code> (not HMAC).</li>
                        <li>Set <strong>Bank name (POS)</strong> to the exact value below — used only to compute the hash in the BLE packet.</li>
                        <li>Set <strong>Masked account suffix</strong> to <code class="bg-white/80 px-1 rounded">{{ $terminal->masked_account_suffix }}</code> (last 4 digits of your settlement account).</li>
                        <li>Do <strong>not</strong> put the full account number in the BLE broadcast — the server supplies it after verify.</li>
                        <li>Poll <code class="bg-white/80 px-1 rounded text-xs">GET /api/v1/broadcast/sessions/{uuid}?terminal_id=…</code> with header <code class="bg-white/80 px-1 rounded text-xs">X-Terminal-Api-Key</code> until status is <code class="bg-white/80 px-1 rounded">paid</code>.</li>
                    </ol>
                </div>

                @if($revealedSigningKey)
                    <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <p class="text-sm text-yellow-900 font-medium">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Copy your signing key now. For security it is only shown immediately after enable or regenerate.
                        </p>
                    </div>
                @endif

                <div class="space-y-4">
                    @foreach([
                        ['label' => 'Terminal ID', 'value' => $terminal->terminal_id, 'id' => 'terminal-id'],
                        ['label' => 'Merchant ID', 'value' => $terminal->merchant_id, 'id' => 'merchant-id'],
                        ['label' => 'API key', 'value' => $terminal->api_key, 'id' => 'broadcast-api-key'],
                        ['label' => 'Signature algorithm (POS)', 'value' => 'ed25519', 'id' => 'signature-alg'],
                        ['label' => 'Bank name (POS — must match exactly)', 'value' => $terminal->bank_name, 'id' => 'bank-name-pos'],
                        ['label' => 'Masked account suffix (POS)', 'value' => $terminal->masked_account_suffix, 'id' => 'masked-suffix-pos'],
                    ] as $field)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $field['label'] }}</label>
                            <div class="flex items-center gap-2">
                                <input type="text" readonly value="{{ $field['value'] }}" id="{{ $field['id'] }}"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 font-mono text-sm">
                                <button type="button" onclick="copyField('{{ $field['id'] }}')" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                                    Copy
                                </button>
                            </div>
                        </div>
                    @endforeach

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Signing key (broadcast)</label>
                        @if($revealedSigningKey)
                            <div class="flex items-center gap-2">
                                <input type="text" readonly value="{{ $revealedSigningKey }}" id="signing-key"
                                    class="flex-1 px-3 py-2 border border-amber-300 rounded-lg bg-amber-50 font-mono text-sm">
                                <button type="button" onclick="copyField('signing-key')" class="px-4 py-2 bg-amber-100 text-amber-900 rounded-lg hover:bg-amber-200 text-sm">
                                    Copy
                                </button>
                            </div>
                        @else
                            <p class="text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                Hidden for security. Regenerate only if your POS was reset or the key was lost.
                            </p>
                            <form method="POST" action="{{ route('business.pay-at-shop.regenerate-signing-key') }}" class="mt-3"
                                onsubmit="return confirm('Regenerate signing key? Update every POS device — old broadcasts will fail verification.')">
                                @csrf
                                <button type="submit" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-sync-alt mr-2"></i> Regenerate signing key
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-gray-100">
                    <h4 class="text-sm font-semibold text-gray-900 mb-2">Settlement account (from CheckoutPay)</h4>
                    <p class="text-xs text-gray-500 mb-3">
                        After a customer scans your POS broadcast, CheckoutNow calls verify and receives your merchant name, bank, and account for the transfer screen.
                    </p>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-gray-500">Account</dt>
                            <dd class="font-mono font-medium text-gray-900">{{ $settlement['account_number'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Bank</dt>
                            <dd class="font-medium text-gray-900">{{ $settlement['bank_name'] }}</dd>
                        </div>
                        @if($settlement['bank_code'])
                            <div>
                                <dt class="text-gray-500">NIP bank code</dt>
                                <dd class="font-mono font-medium text-gray-900">{{ $settlement['bank_code'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        @endif
    @endif
</div>

<script>
function copyField(id) {
    const input = document.getElementById(id);
    if (!input) return;
    navigator.clipboard.writeText(input.value).then(function () {
        alert('Copied to clipboard');
    });
}
</script>
@endsection

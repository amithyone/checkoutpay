<div id="wallet-support-fields" class="space-y-4 border border-teal-200 bg-teal-50/40 rounded-lg p-4 {{ in_array(old('role', $role ?? ''), ['wallet_support']) ? '' : 'hidden' }}">
    <h4 class="text-sm font-semibold text-teal-900">Wallet support alerts</h4>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp number</label>
        <input type="text" name="whatsapp_e164" value="{{ old('whatsapp_e164', $whatsapp ?? '') }}"
            placeholder="e.g. 08012345678"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
        <p class="mt-1 text-xs text-gray-500">Used for new wallet signup alerts. Required for wallet support role.</p>
    </div>
    <div>
        <label class="flex items-center space-x-2">
            <input type="checkbox" name="notify_wallet_signup" value="1"
                {{ old('notify_wallet_signup', $notifySignup ?? true) ? 'checked' : '' }}
                class="rounded border-gray-300 text-primary focus:ring-primary">
            <span class="text-sm text-gray-700">Notify on new wallet signup</span>
        </label>
    </div>
</div>

<div id="page-permissions-fields" class="space-y-4 border border-gray-200 rounded-lg p-4 {{ in_array(old('role', $role ?? ''), ['wallet_support', 'staff', 'support']) ? '' : 'hidden' }}">
    <h4 class="text-sm font-semibold text-gray-900">Admin page access</h4>
    <p class="text-xs text-gray-500">Leave unchecked to use role defaults. Check pages this user may access.</p>
    @php
        $selected = old('page_permissions', $pagePermissions ?? []);
        $grouped = collect($pageCatalog ?? [])->groupBy('group');
    @endphp
    @foreach($grouped as $group => $items)
        <div>
            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">{{ $group }}</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                @foreach($items as $item)
                    <label class="flex items-center space-x-2 text-sm">
                        <input type="checkbox" name="page_permissions[]" value="{{ $item['key'] }}"
                            {{ in_array($item['key'], $selected, true) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-primary focus:ring-primary">
                        <span>{{ $item['label'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.querySelector('select[name="role"]');
    const walletFields = document.getElementById('wallet-support-fields');
    const pageFields = document.getElementById('page-permissions-fields');
    if (!roleSelect) return;
    function sync() {
        const role = roleSelect.value;
        if (walletFields) walletFields.classList.toggle('hidden', role !== 'wallet_support');
        if (pageFields) pageFields.classList.toggle('hidden', !['wallet_support', 'staff', 'support'].includes(role));
    }
    roleSelect.addEventListener('change', sync);
    sync();
});
</script>

@extends('layouts.admin')

@section('title', 'Transaction #' . $transaction->id)
@section('page-title', 'Wallet transaction #' . $transaction->id)

@section('content')
@php
    $meta = is_array($transaction->meta) ? $transaction->meta : [];
    $mevonpayPayload = is_array($meta['mevonpay'] ?? null) ? $meta['mevonpay'] : null;
    $mevonApi = is_array($mevonpayPayload['api_response'] ?? null) ? $mevonpayPayload['api_response'] : [];
    $bucketClass = match ($payoutBucket) {
        'failed' => 'bg-red-100 text-red-800',
        'pending' => 'bg-amber-100 text-amber-800',
        'successful' => 'bg-green-100 text-green-800',
        default => 'bg-gray-100 text-gray-700',
    };
    $vtuPending = (bool) ($electricityMeta['vtu_pending'] ?? false);
    $vtuRefunded = (bool) ($electricityMeta['vtu_refunded'] ?? false);
    $electricityToken = trim((string) ($electricityMeta['electricity_token'] ?? ''));
    $vtuStatusClass = match (true) {
        $vtuRefunded => 'bg-red-100 text-red-800',
        $vtuPending => 'bg-amber-100 text-amber-800',
        $electricityToken !== '' => 'bg-green-100 text-green-800',
        default => 'bg-gray-100 text-gray-700',
    };
    $vtuStatusLabel = match (true) {
        $vtuRefunded => 'Refunded',
        $vtuPending => 'Pending token',
        $electricityToken !== '' => 'Token delivered',
        default => trim((string) ($electricityMeta['vtu_status'] ?? '')) !== ''
            ? ucfirst(str_replace('-', ' ', (string) $electricityMeta['vtu_status']))
            : 'Unknown',
    };
@endphp
<div class="space-y-6">
    @include('admin.whatsapp-wallet.partials.nav')

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        @if($transaction->wallet)
            <a href="{{ route('admin.whatsapp-wallet.wallets.show', $transaction->wallet) }}" class="text-sm text-primary hover:underline">
                Wallet {{ $transaction->wallet->phone_e164 }}
            </a>
        @endif
        <a href="{{ route('admin.whatsapp-wallet.transactions.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
            <i class="fas fa-arrow-left mr-1"></i> Back to list
        </a>
        <a href="{{ $auditUrl }}" class="text-sm text-primary hover:underline" target="_blank" rel="noopener">MevonPay audit</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500">Amount</p>
            <p class="text-xl font-bold text-gray-900">₦{{ number_format((float) $transaction->amount, 2) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500">Type</p>
            <p class="text-lg font-semibold text-gray-900">{{ str_replace('_', ' ', $transaction->type) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500">@if($isElectricity) VTU status @else Payout status @endif</p>
            @if($isElectricity)
                <p class="mt-1"><span class="inline-flex px-2 py-1 rounded text-sm font-medium {{ $vtuStatusClass }}">{{ $vtuStatusLabel }}</span></p>
                @if($electricityToken !== '')
                    <p class="text-xs text-gray-600 mt-1 font-mono break-all">{{ $electricityToken }}</p>
                @endif
            @else
                <p class="mt-1"><span class="inline-flex px-2 py-1 rounded text-sm font-medium {{ $bucketClass }}">{{ ucfirst($payoutBucket) }}</span></p>
                @if($transaction->isReversed())
                    <p class="text-xs text-red-600 mt-1">Reversed {{ $meta['reversed_at'] ?? '' }}</p>
                @endif
            @endif
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500">Balance after</p>
            <p class="text-lg font-semibold text-gray-900">{{ $transaction->balance_after !== null ? '₦'.number_format((float) $transaction->balance_after, 2) : '—' }}</p>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Details</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Wallet phone</dt>
                <dd class="font-medium text-gray-900">{{ $transaction->wallet?->phone_e164 ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Wallet balance (now)</dt>
                <dd class="font-medium text-gray-900">₦{{ number_format((float) ($transaction->wallet?->balance ?? 0), 2) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">External reference</dt>
                <dd class="font-mono text-gray-900 break-all">{{ $transaction->external_reference ?: '—' }}</dd>
            </div>
            @if($isElectricity)
            <div>
                <dt class="text-gray-500">VTU request ID</dt>
                <dd class="font-mono text-gray-900 break-all">{{ $electricityMeta['vtu_request_id'] ?? $electricityMeta['vtu_provider_reference'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">VTU order ID</dt>
                <dd class="font-mono text-gray-900 break-all">{{ $electricityMeta['vtu_order_id'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Disco / service</dt>
                <dd class="text-gray-900">{{ $electricityMeta['service_id'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Meter number</dt>
                <dd class="font-mono text-gray-900 break-all">{{ $electricityMeta['meter_number'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Customer name</dt>
                <dd class="text-gray-900">{{ $electricityMeta['customer_name'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Units</dt>
                <dd class="text-gray-900">{{ $electricityMeta['electricity_units'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Electricity token</dt>
                <dd class="font-mono text-gray-900 break-all">{{ $electricityToken !== '' ? $electricityToken : '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Provider status</dt>
                <dd class="text-gray-900">{{ $electricityMeta['vtu_status'] ?? '—' }}</dd>
            </div>
            @else
            <div>
                <dt class="text-gray-500">Session ID</dt>
                <dd class="font-mono text-gray-900 break-all">{{ $mevonApi['sessionId'] ?? $meta['payout_session_id'] ?? '—' }}</dd>
            </div>
            @endif
            @if(!$isElectricity)
            <div>
                <dt class="text-gray-500">Mevon response</dt>
                <dd class="text-gray-900">
                    {{ $mevonApi['responseCode'] ?? $meta['payout_response_code'] ?? '—' }}
                    —
                    {{ $mevonApi['responseMessage'] ?? $meta['payout_response_message'] ?? '—' }}
                </dd>
            </div>
            @if(!empty($mevonApi['contractReference']))
            <div>
                <dt class="text-gray-500">Contract reference</dt>
                <dd class="font-mono text-gray-900 break-all">{{ $mevonApi['contractReference'] }}</dd>
            </div>
            @endif
            @endif
            @if(!$isElectricity)
            <div>
                <dt class="text-gray-500">Beneficiary</dt>
                <dd class="text-gray-900">{{ $transaction->counterparty_account_name ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Account / bank</dt>
                <dd class="text-gray-900">{{ $transaction->counterparty_account_number ?: '—' }} / {{ $meta['bank_name'] ?? $transaction->counterparty_bank_code ?? '—' }}</dd>
            </div>
            @endif
            <div>
                <dt class="text-gray-500">Created</dt>
                <dd class="text-gray-900">{{ $transaction->created_at?->format('M j, Y H:i:s') }}</dd>
            </div>
            @if(!$isElectricity)
            <div>
                <dt class="text-gray-500">Payout API</dt>
                <dd class="text-gray-900">{{ $meta['payout_api'] ?? '—' }}</dd>
            </div>
            @endif
        </dl>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Actions</h3>
        </div>
        <div class="flex flex-wrap gap-3">
            @if($isElectricity)
                <button type="button" id="btn-check-vtu-electricity-status"
                    class="bg-amber-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-amber-700">
                    Check VTU electricity status
                </button>
            @elseif($transaction->type === \App\Models\WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT)
                <button type="button" id="btn-check-mevon-status"
                    class="bg-primary text-white px-4 py-2 rounded-lg text-sm hover:opacity-90">
                    Check MevonPay status
                </button>
            @endif
            @if($canManualRefund && auth('admin')->user()?->isSuperAdmin())
                <form method="POST" action="{{ route('admin.whatsapp-wallet.transactions.manual-refund', $transaction) }}"
                    onsubmit="return confirm('Credit the customer wallet and mark this payout as reversed?');">
                    @csrf
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700">
                        Manual refund (pending)
                    </button>
                </form>
            @endif
        </div>
        <p id="status-check-message" class="mt-3 text-sm text-gray-600 hidden"></p>
        @if($isElectricity)
            @unless($electricityStatusCheckAvailable ?? false)
                <p class="mt-2 text-xs text-amber-700">VTU.ng is not configured (<code>VTU_NG_*</code>). Stored meta below still applies.</p>
            @endunless
        @elseif(!$statusCheckAvailable)
            <p class="mt-2 text-xs text-amber-700">Provider status API is not configured (<code>MEVONPAY_TRANSFER_STATUS_PATH</code>). Stored meta and ledger entries below still apply.</p>
        @endif
    </div>

    @if($transaction->mevonLedgerEntries->isNotEmpty())
        <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm overflow-hidden">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">MevonPay ledger</h3>
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-gray-600">When</th>
                        <th class="px-3 py-2 text-left text-gray-600">Flow</th>
                        <th class="px-3 py-2 text-right text-gray-600">Gross</th>
                        <th class="px-3 py-2 text-left text-gray-600">Bucket</th>
                        <th class="px-3 py-2 text-left text-gray-600">Reference</th>
                        <th class="px-3 py-2 text-left text-gray-600"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($transaction->mevonLedgerEntries as $entry)
                        <tr>
                            <td class="px-3 py-2">{{ $entry->occurred_at?->format('M j, Y H:i') }}</td>
                            <td class="px-3 py-2">{{ $entry->flowTypeLabel() }}</td>
                            <td class="px-3 py-2 text-right">₦{{ number_format((float) $entry->gross_amount, 2) }}</td>
                            <td class="px-3 py-2">{{ $entry->payout_bucket ?? '—' }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $entry->payout_reference ?? $entry->external_reference ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs">
                                <a href="{{ $entry->adminMevonAuditUrl() }}" class="text-primary hover:underline">MevonPay audit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($mevonpayPayload)
        <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">MevonPay payout response</h3>
            <p class="text-xs text-gray-500 mb-3">Full provider payload (session ID, response codes, and transfer fields).</p>
            <pre class="text-xs bg-gray-50 border border-gray-200 rounded-lg p-4 overflow-x-auto">{{ json_encode($mevonpayPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    @endif

    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Meta (raw)</h3>
        <pre class="text-xs bg-gray-50 border border-gray-200 rounded-lg p-4 overflow-x-auto">{{ json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
</div>

@push('scripts')
<script>
(function () {
    function wireStatusCheck(btnId, url, reloadOnSuccess) {
        var btn = document.getElementById(btnId);
        var msg = document.getElementById('status-check-message');
        if (!btn || !msg) return;

        btn.addEventListener('click', function () {
            btn.disabled = true;
            msg.classList.remove('hidden');
            msg.textContent = 'Checking…';

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': @json(csrf_token()),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var text = data.message || 'Done.';
                    if (data.vtu_status) {
                        text += ' Status: ' + data.vtu_status + '.';
                    }
                    if (data.electricity_token) {
                        text += ' Token: ' + data.electricity_token + '.';
                    }
                    if (data.bucket) {
                        text += ' Bucket: ' + data.bucket + '.';
                    }
                    if (data.transaction_status) {
                        text += ' Provider status: ' + data.transaction_status + '.';
                    }
                    if (data.response_code) {
                        text += ' Code: ' + data.response_code + '.';
                    }
                    if (data.notified) {
                        text += ' Customer notified on WhatsApp.';
                    }
                    if (data.auto_refund && data.auto_refund.message) {
                        text += ' ' + data.auto_refund.message;
                    }
                    msg.textContent = text;
                    if (reloadOnSuccess(data)) {
                        setTimeout(function () { window.location.reload(); }, 1500);
                    }
                })
                .catch(function () {
                    msg.textContent = 'Request failed.';
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    wireStatusCheck(
        'btn-check-mevon-status',
        @json(route('admin.whatsapp-wallet.transactions.check-status', $transaction)),
        function (data) { return data.available && data.bucket; }
    );

    wireStatusCheck(
        'btn-check-vtu-electricity-status',
        @json(route('admin.whatsapp-wallet.transactions.check-electricity-status', $transaction)),
        function (data) {
            return (data.completed || data.failed || (data.requery_ok && data.vtu_status)) && !(data.skipped);
        }
    );
})();
</script>
@endpush
@endsection

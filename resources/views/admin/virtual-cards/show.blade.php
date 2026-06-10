@extends('layouts.admin')

@section('title', 'Card Request #'.$card->id)
@section('page-title', 'Card Management #'.$card->id)

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.virtual-cards.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
            <i class="fas fa-arrow-left mr-1"></i> Back to list
        </a>
        @include('admin.virtual-cards._status-badge', ['status' => $card->status])
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Summary</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Request ID</dt>
                <dd class="font-medium text-gray-900">#{{ $card->id }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Reference</dt>
                <dd class="font-mono text-xs text-gray-900">{{ $card->external_reference ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Mevon reference</dt>
                <dd class="font-mono text-xs text-gray-900">{{ $card->provider_reference ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Provider card ID</dt>
                <dd class="font-mono text-xs text-gray-900">{{ $card->card_external_id ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Fee</dt>
                <dd class="font-medium text-gray-900">${{ number_format($card->fee_usd, 2) }} / ₦{{ number_format($card->fee_ngn, 2) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">FX rate used</dt>
                <dd class="text-gray-900">{{ $card->fx_rate_used ? number_format($card->fx_rate_used, 4) : '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Created</dt>
                <dd class="text-gray-900">{{ $card->created_at->format('M d, Y H:i:s') }}</dd>
            </div>
            @if($card->activated_at)
            <div>
                <dt class="text-gray-500">Activated</dt>
                <dd class="text-gray-900">{{ $card->activated_at->format('M d, Y H:i:s') }}</dd>
            </div>
            @endif
            @if($card->handledBy)
            <div>
                <dt class="text-gray-500">Handled by</dt>
                <dd class="text-gray-900">{{ $card->handledBy->name }}</dd>
            </div>
            @endif
            @if($card->failure_reason)
            <div class="sm:col-span-2 lg:col-span-3">
                <dt class="text-gray-500">Failure reason</dt>
                <dd class="text-red-700">{{ $card->failure_reason }}</dd>
            </div>
            @endif
        </dl>
    </div>

    @if($card->wallet)
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Customer / wallet</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Phone</dt>
                <dd class="font-medium text-gray-900">{{ $card->wallet->phone_e164 }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Display name</dt>
                <dd class="text-gray-900">{{ $card->wallet->displayName() ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Tier 2 KYC</dt>
                <dd>
                    @if($card->wallet->isTier2())
                        <span class="text-green-700 font-medium">Verified</span>
                    @else
                        <span class="text-amber-700 font-medium">Not Tier 2</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Wallet balance</dt>
                <dd class="text-gray-900">₦{{ number_format((float) $card->wallet->balance, 2) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">KYC name</dt>
                <dd class="text-gray-900">{{ trim(($card->wallet->kyc_fname ?? '').' '.($card->wallet->kyc_lname ?? '')) ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">KYC email</dt>
                <dd class="text-gray-900">{{ $card->wallet->kyc_email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Date of birth</dt>
                <dd class="text-gray-900">{{ $card->wallet->kyc_dob?->format('Y-m-d') ?? '—' }}</dd>
            </div>
        </dl>
    </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Card details</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Card name</dt>
                <dd class="text-gray-900">{{ $card->card_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Home number</dt>
                <dd class="text-gray-900">{{ $card->home_number ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-gray-500">Home address</dt>
                <dd class="text-gray-900">{{ $card->home_address ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Fee transaction</h3>
        @if($feeTransaction)
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Amount debited</dt>
                <dd class="font-medium text-gray-900">₦{{ number_format((float) $feeTransaction->amount, 2) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Balance after</dt>
                <dd class="text-gray-900">₦{{ number_format((float) $feeTransaction->balance_after, 2) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Refunded</dt>
                <dd>
                    @php $refunded = is_array($feeTransaction->meta) && ($feeTransaction->meta['refunded'] ?? false); @endphp
                    @if($refunded)
                        <span class="text-green-700 font-medium">Yes</span>
                        @if(!empty($feeTransaction->meta['refund_reason']))
                            <span class="text-gray-500 text-xs block">{{ $feeTransaction->meta['refund_reason'] }}</span>
                        @endif
                    @else
                        <span class="text-gray-700">No</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Transaction date</dt>
                <dd class="text-gray-900">{{ $feeTransaction->created_at->format('M d, Y H:i:s') }}</dd>
            </div>
        </dl>
        @else
        <p class="text-sm text-gray-500">No fee transaction found for this reference.</p>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between gap-3 mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Event log</h3>
            <a href="{{ route('admin.virtual-cards.logs', ['request_id' => $card->id]) }}" class="text-sm text-primary hover:underline">View all logs</a>
        </div>
        @if(($requestLogs ?? collect())->isNotEmpty())
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @foreach($requestLogs as $log)
                    <div class="border border-gray-100 rounded-lg p-3 text-sm">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="text-xs text-gray-500">{{ $log->created_at?->format('M d, H:i:s') }}</span>
                            <span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $log->event }}</span>
                            @if($log->level === 'error')
                                <span class="text-xs text-red-700 font-medium">error</span>
                            @elseif($log->level === 'warning')
                                <span class="text-xs text-amber-700 font-medium">warning</span>
                            @endif
                        </div>
                        <p class="text-gray-900">{{ $log->message }}</p>
                        <div class="mt-2">
                            @include('admin.virtual-cards._log-context', ['log' => $log])
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500">No structured logs for this request yet.</p>
        @endif
    </div>

    @if($card->status === \App\Models\VirtualCardRequest::STATUS_ACTIVE)
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Card transaction history (MevonPay)</h3>
        @if(!empty($cardTransactions))
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase">When</th>
                            <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase">Merchant / Description</th>
                            <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase text-right">Amount (USD)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($cardTransactions as $txn)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">
                                {{ !empty($txn['created_at']) ? \Carbon\Carbon::parse($txn['created_at'])->format('Y-m-d H:i') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-900 font-medium">
                                {{ $txn['label'] ?? '—' }}
                                @if(!empty($txn['reference']))
                                    <span class="text-xs text-gray-400 block font-mono">Ref: {{ $txn['reference'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600 font-mono">
                                {{ strtoupper($txn['type'] ?? 'payment') }} ({{ $txn['direction'] ?? 'debit' }})
                            </td>
                            <td class="px-4 py-3">
                                @if(($txn['status'] ?? 'success') === 'success')
                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded font-semibold">Success</span>
                                @else
                                    <span class="bg-red-100 text-red-800 text-xs px-2 py-0.5 rounded font-semibold">{{ ucfirst($txn['status'] ?? 'failed') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-medium {{ ($txn['direction'] ?? 'debit') === 'credit' ? 'text-green-700' : 'text-gray-900' }}">
                                {{ ($txn['direction'] ?? 'debit') === 'credit' ? '+' : '-' }}${{ number_format((float) ($txn['amount_usd'] ?? 0), 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500">No card transactions found from MevonPay.</p>
        @endif
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Request payload</h3>
            <pre class="text-xs bg-gray-50 border border-gray-200 rounded-lg p-3 overflow-x-auto max-h-64">{{ json_encode($card->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '—' }}</pre>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Response payload</h3>
            <pre class="text-xs bg-gray-50 border border-gray-200 rounded-lg p-3 overflow-x-auto max-h-64">{{ json_encode($card->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '—' }}</pre>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Admin notes</h3>
        <form method="POST" action="{{ route('admin.virtual-cards.update-notes', $card) }}">
            @csrf
            <textarea name="admin_notes" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                placeholder="Internal notes for this card request…">{{ old('admin_notes', $card->admin_notes) }}</textarea>
            <button type="submit" class="mt-3 bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-900 text-sm">
                Save notes
            </button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
        <div class="flex flex-wrap gap-4">
            @if($canMarkActive)
            <form method="POST" action="{{ route('admin.virtual-cards.mark-active', $card) }}"
                onsubmit="return confirm('Mark this card as active?');">
                @csrf
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm">
                    <i class="fas fa-check mr-1"></i> Mark as active
                </button>
            </form>
            @endif

            @if($canMarkFailed)
            <form method="POST" action="{{ route('admin.virtual-cards.mark-failed', $card) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <input type="text" name="failure_reason" required maxlength="500" placeholder="Failure reason"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[240px]">
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm"
                    onclick="return confirm('Mark this request as failed?');">
                    Mark as failed
                </button>
            </form>
            @endif

            @if($canRetry)
            <form method="POST" action="{{ route('admin.virtual-cards.retry', $card) }}"
                onsubmit="return confirm('Resend create request to MevonPay? Wallet will not be debited again.');">
                @csrf
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 text-sm">
                    <i class="fas fa-redo mr-1"></i> Retry provider request
                </button>
            </form>
            @endif

            @if($canRefund)
            <form method="POST" action="{{ route('admin.virtual-cards.refund-fee', $card) }}"
                onsubmit="return confirm('Refund the card fee to the customer wallet?');">
                @csrf
                <button type="submit" class="bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 text-sm">
                    <i class="fas fa-undo mr-1"></i> Refund fee
                </button>
            </form>
            @endif

            @if(!$canMarkActive && !$canMarkFailed && !$canRetry && !$canRefund)
            <p class="text-sm text-gray-500">No actions available for this status.</p>
            @endif
        </div>
    </div>
</div>
@endsection

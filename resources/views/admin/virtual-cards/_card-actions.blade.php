@php
    $providerLabel = ($card->provider ?? 'mevonpay') === 'cashwyre' ? 'Cashwyre' : 'MevonPay';
    $compact = $compact ?? false;
    $flags = $flags ?? [
        'canRetry' => $canRetry ?? false,
        'canRetryWebhookSync' => $canRetryWebhookSync ?? false,
        'canRefund' => $canRefund ?? false,
        'canMarkActive' => $canMarkActive ?? false,
        'canMarkFailed' => $canMarkFailed ?? false,
        'isPreparing' => ($card->status ?? '') === \App\Models\VirtualCardRequest::STATUS_PREPARING,
    ];
    $btn = $compact
        ? 'text-xs px-2 py-1 rounded'
        : 'text-sm px-4 py-2 rounded-lg';
@endphp

<div class="{{ $compact ? 'flex flex-wrap gap-1' : 'flex flex-wrap gap-3' }}">
    @if($flags['canRetry'] ?? false)
        <form method="POST" action="{{ route('admin.virtual-cards.retry', $card) }}"
            onsubmit="return confirm('Resend create request to {{ $providerLabel }}? Customer wallet will not be debited again.');">
            @csrf
            <button type="submit" class="{{ $btn }} bg-indigo-600 text-white hover:bg-indigo-700">
                @if($compact)
                    Retry {{ $providerLabel }}
                @else
                    <i class="fas fa-redo mr-1"></i> Retry {{ $providerLabel }} request
                @endif
            </button>
        </form>
    @endif

    @if($flags['canRetryWebhookSync'] ?? false)
        <form method="POST" action="{{ route('admin.virtual-cards.retry-webhook-sync', $card) }}"
            onsubmit="return confirm('Replay stored {{ $providerLabel }} card-created webhook for this request?');">
            @csrf
            <button type="submit" class="{{ $btn }} bg-teal-600 text-white hover:bg-teal-700">
                @if($compact)
                    Sync webhook
                @else
                    <i class="fas fa-link mr-1"></i> Sync from {{ $providerLabel }} webhook
                @endif
            </button>
        </form>
    @endif

    @if($flags['canRefund'] ?? false)
        <form method="POST" action="{{ route('admin.virtual-cards.refund-fee', $card) }}"
            onsubmit="return confirm('Refund the card setup fee to the customer wallet?');">
            @csrf
            <button type="submit" class="{{ $btn }} bg-amber-600 text-white hover:bg-amber-700">
                @if($compact)
                    Refund fee
                @else
                    <i class="fas fa-undo mr-1"></i> Refund setup fee
                @endif
            </button>
        </form>
    @endif

    @if(!$compact && ($flags['canMarkActive'] ?? false))
        <form method="POST" action="{{ route('admin.virtual-cards.mark-active', $card) }}"
            onsubmit="return confirm('Mark this card as active manually?');">
            @csrf
            <button type="submit" class="{{ $btn }} bg-green-600 text-white hover:bg-green-700">
                <i class="fas fa-check mr-1"></i> Mark as active
            </button>
        </form>
    @endif

    @if(!$compact && ($flags['canMarkFailed'] ?? false))
        <form method="POST" action="{{ route('admin.virtual-cards.mark-failed', $card) }}" class="flex flex-wrap items-end gap-2">
            @csrf
            <input type="text" name="failure_reason" required maxlength="500" placeholder="Failure reason"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[240px]">
            <button type="submit" class="{{ $btn }} bg-red-600 text-white hover:bg-red-700"
                onclick="return confirm('Mark this request as failed?');">
                Mark as failed
            </button>
        </form>
    @endif
</div>

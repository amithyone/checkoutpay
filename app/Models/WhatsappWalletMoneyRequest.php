<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappWalletMoneyRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_CONSUMER_API = 'consumer_api';

    protected $fillable = [
        'public_id',
        'requester_wallet_id',
        'requester_phone_e164',
        'payer_phone_e164',
        'payer_wallet_id',
        'amount',
        'currency',
        'note',
        'status',
        'channel',
        'expires_at',
        'responded_at',
        'p2p_debit_transaction_id',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'meta' => 'array',
    ];

    public function requesterWallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'requester_wallet_id');
    }

    public function payerWallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'payer_wallet_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}

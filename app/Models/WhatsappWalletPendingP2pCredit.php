<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappWalletPendingP2pCredit extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CLAIMED = 'claimed';

    public const STATUS_REFUNDED = 'refunded';

    protected $table = 'whatsapp_wallet_pending_p2p_credits';

    protected $fillable = [
        'sender_wallet_id',
        'recipient_phone_e164',
        'amount',
        'status',
        'expires_at',
        'claimed_at',
        'refunded_at',
        'sender_debit_transaction_id',
        'sender_refund_transaction_id',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'claimed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'meta' => 'array',
    ];

    public function senderWallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'sender_wallet_id');
    }
}

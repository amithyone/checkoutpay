<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletSaveTogetherContribution extends Model
{
    public const KIND_CONTRIBUTE = 'contribute';

    public const KIND_WITHDRAW = 'withdraw';

    protected $fillable = [
        'pot_id',
        'member_id',
        'amount',
        'kind',
        'whatsapp_wallet_transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function pot(): BelongsTo
    {
        return $this->belongsTo(WalletSaveTogetherPot::class, 'pot_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(WalletSaveTogetherMember::class, 'member_id');
    }
}

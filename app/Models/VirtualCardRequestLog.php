<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualCardRequestLog extends Model
{
    public const UPDATED_AT = null;

    public const LEVEL_INFO = 'info';

    public const LEVEL_WARNING = 'warning';

    public const LEVEL_ERROR = 'error';

    protected $fillable = [
        'virtual_card_request_id',
        'whatsapp_wallet_id',
        'level',
        'event',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(VirtualCardRequest::class, 'virtual_card_request_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }
}

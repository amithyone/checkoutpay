<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumerAppSessionEvent extends Model
{
    public const TYPE_LOGIN = 'login';

    public const TYPE_LOGOUT = 'logout';

    public const TYPE_TRANSFER_P2P = 'transfer_p2p';

    public const TYPE_TRANSFER_BANK = 'transfer_bank';

    public const TYPE_DEVICE_STEPUP = 'device_stepup';

    public const TYPE_PASSKEY_REGISTER = 'passkey_register';

    public const TYPE_HEARTBEAT = 'heartbeat';

    protected $fillable = [
        'consumer_app_session_id',
        'consumer_wallet_api_account_id',
        'whatsapp_wallet_id',
        'phone_e164',
        'event_type',
        'summary',
        'meta',
        'ip_address',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ConsumerAppSession::class, 'consumer_app_session_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ConsumerWalletApiAccount::class, 'consumer_wallet_api_account_id');
    }
}

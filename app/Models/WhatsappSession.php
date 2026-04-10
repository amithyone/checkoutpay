<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappSession extends Model
{
    public const STATE_WELCOME = 'welcome';

    public const STATE_AWAIT_EMAIL = 'await_email';

    public const STATE_AWAIT_OTP = 'await_otp';

    public const STATE_LINKED = 'linked';

    protected $fillable = [
        'phone_e164',
        'remote_jid',
        'evolution_instance',
        'state',
        'pending_email',
        'otp_code_hash',
        'otp_expires_at',
        'otp_attempts',
        'magic_link_token_hash',
        'magic_link_expires_at',
        'renter_id',
        'chat_flow',
        'chat_context',
        'bot_paused',
    ];

    protected $casts = [
        'otp_expires_at' => 'datetime',
        'otp_attempts' => 'integer',
        'magic_link_expires_at' => 'datetime',
        'chat_context' => 'array',
        'bot_paused' => 'boolean',
    ];

    public function renter(): BelongsTo
    {
        return $this->belongsTo(Renter::class);
    }
}

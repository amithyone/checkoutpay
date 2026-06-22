<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsumerAppSession extends Model
{
    public const LOGIN_PIN = 'pin';

    public const LOGIN_OTP = 'otp';

    public const LOGIN_PASSKEY = 'passkey';

    public const LOGIN_DEVICE_BIND = 'device_bind';

    public const LOGIN_REGISTER = 'register';

    protected $fillable = [
        'session_uuid',
        'consumer_wallet_api_account_id',
        'whatsapp_wallet_id',
        'phone_e164',
        'login_method',
        'platform',
        'app_version',
        'device_label',
        'ip_address',
        'user_agent',
        'personal_access_token_id',
        'started_at',
        'ended_at',
        'last_seen_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ConsumerWalletApiAccount::class, 'consumer_wallet_api_account_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ConsumerAppSessionEvent::class);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    public function loginMethodLabel(): string
    {
        return match ($this->login_method) {
            self::LOGIN_PIN => 'Wallet PIN',
            self::LOGIN_OTP => 'OTP',
            self::LOGIN_PASSKEY => 'Passkey',
            self::LOGIN_DEVICE_BIND => 'New device bind',
            self::LOGIN_REGISTER => 'Registration',
            default => $this->login_method,
        };
    }
}

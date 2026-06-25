<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

/**
 * Sanctum token holder for the consumer mobile wallet API (maps 1:1 to WhatsappWallet).
 */
class ConsumerWalletApiAccount extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasApiTokens;

    protected $fillable = [
        'whatsapp_wallet_id',
        'phone_e164',
        'fcm_token',
        'fcm_platform',
        'last_app_active_at',
        'transfer_lock_until',
    ];

    protected $casts = [
        'fcm_token_updated_at' => 'datetime',
        'last_app_active_at' => 'datetime',
        'transfer_lock_until' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(ConsumerTrustedDevice::class, 'consumer_wallet_api_account_id');
    }

    public function isTransferLocked(): bool
    {
        return $this->transfer_lock_until !== null && $this->transfer_lock_until->isFuture();
    }

    /**
     * @return array{token: string, platform: ?string}|null
     */
    public function pushDeliveryTarget(): ?array
    {
        $token = trim((string) ($this->fcm_token ?? ''));
        $platform = trim((string) ($this->fcm_platform ?? ''));

        if ($token === '' || $platform === '' || $platform === 'web') {
            return null;
        }

        return [
            'token' => $token,
            'platform' => $platform,
        ];
    }

    /**
     * Token-only accounts have no password; satisfy the contract for middleware (e.g. throttle) safely.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * @param  list<string>  $failedTokens
     */
    public static function clearFcmTokenIfInvalid(string $token, array $failedTokens): void
    {
        if ($token === '' || ! in_array($token, $failedTokens, true)) {
            return;
        }

        static::query()
            ->where('fcm_token', $token)
            ->update([
                'fcm_token' => null,
                'fcm_platform' => null,
                'fcm_token_updated_at' => null,
            ]);
    }
}

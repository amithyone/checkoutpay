<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappWallet extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const TIER_WHATSAPP_ONLY = 1;

    public const TIER_RUBIES_VA = 2;

    protected $fillable = [
        'phone_e164',
        'renter_id',
        'tier',
        'balance',
        'daily_transfer_total',
        'daily_transfer_for_date',
        'pin_hash',
        'pin_set_at',
        'pin_failed_attempts',
        'pin_locked_until',
        'sender_name',
        'kyc_verified_at',
        'mevon_virtual_account_number',
        'mevon_bank_name',
        'mevon_bank_code',
        'mevon_reference',
        'tier2_provisioned_at',
        'kyc_fname',
        'kyc_lname',
        'kyc_gender',
        'kyc_dob',
        'kyc_bvn',
        'kyc_email',
        'status',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'daily_transfer_total' => 'decimal:2',
        'daily_transfer_for_date' => 'date',
        'pin_set_at' => 'datetime',
        'pin_failed_attempts' => 'integer',
        'pin_locked_until' => 'datetime',
        'kyc_verified_at' => 'datetime',
        'tier2_provisioned_at' => 'datetime',
        'kyc_dob' => 'date',
        'tier' => 'integer',
    ];

    public function renter(): BelongsTo
    {
        return $this->belongsTo(Renter::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WhatsappWalletTransaction::class, 'whatsapp_wallet_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasPin(): bool
    {
        return $this->pin_hash !== null && $this->pin_hash !== '';
    }

    public function normalizedSenderName(): ?string
    {
        $n = trim((string) $this->sender_name);

        return $n !== '' ? $n : null;
    }

    /**
     * Email used for transfer confirmation OTP (linked renter account, else Tier 2 KYC email).
     */
    public function resolveOtpEmail(): ?string
    {
        $this->loadMissing('renter');
        $e = $this->renter?->email;
        if (is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL)) {
            return strtolower(trim($e));
        }
        $k = trim((string) $this->kyc_email);
        if ($k !== '' && filter_var($k, FILTER_VALIDATE_EMAIL)) {
            return strtolower($k);
        }

        return null;
    }

    public function isPinLocked(): bool
    {
        return $this->pin_locked_until !== null && $this->pin_locked_until->isFuture();
    }

    public function tier1MaxBalance(): float
    {
        return (float) config('whatsapp.wallet.tier1_max_balance', 50000);
    }

    public function tier1DailyOutLimit(): float
    {
        return (float) config('whatsapp.wallet.tier1_daily_transfer_limit', 50000);
    }

    public function resetDailyTransferIfNeeded(): void
    {
        $today = Carbon::today()->toDateString();
        $for = $this->daily_transfer_for_date;
        if ($for === null) {
            $this->daily_transfer_total = 0;
            $this->daily_transfer_for_date = $today;
            $this->save();

            return;
        }
        $forStr = $for instanceof Carbon ? $for->toDateString() : (string) $for;
        if ($forStr !== $today) {
            $this->daily_transfer_total = 0;
            $this->daily_transfer_for_date = $today;
            $this->save();
        }
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function canCredit(float $amount): array
    {
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Invalid amount.'];
        }

        if ((int) $this->tier === self::TIER_WHATSAPP_ONLY) {
            $newBal = (float) $this->balance + $amount;
            if ($newBal > $this->tier1MaxBalance() + 0.0001) {
                return [
                    'ok' => false,
                    'message' => 'Tier 1 wallet cannot exceed ₦'.number_format($this->tier1MaxBalance(), 2).
                        '. Upgrade to Tier 2 (*UPGRADE*) or spend first.',
                ];
            }
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function canDebit(float $amount): array
    {
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Invalid amount.'];
        }

        if ((float) $this->balance + 0.0001 < $amount) {
            return ['ok' => false, 'message' => 'Insufficient balance.'];
        }

        if ((int) $this->tier === self::TIER_WHATSAPP_ONLY) {
            $this->resetDailyTransferIfNeeded();
            if ($this->daily_transfer_total + $amount > $this->tier1DailyOutLimit() + 0.0001) {
                return [
                    'ok' => false,
                    'message' => 'Tier 1 daily send limit is ₦'.number_format($this->tier1DailyOutLimit(), 2).
                        '. Try tomorrow or upgrade (*UPGRADE*).',
                ];
            }
        }

        return ['ok' => true];
    }
}

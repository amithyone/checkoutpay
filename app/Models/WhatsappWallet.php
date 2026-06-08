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
        'pay_code',
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
        'mevon_account_name',
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
        'card_home_number',
        'card_home_address',
        'rubies_account_type',
        'kyc_cac',
        'transfer_email_otp_enabled',
        'notify_card_created_email',
        'notify_card_created_whatsapp',
        'notify_card_transaction_email',
        'notify_card_transaction_whatsapp',
        'status',
        'admin_bot_paused',
        'support_whatsapp_welcome_sent_at',
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
        'transfer_email_otp_enabled' => 'boolean',
        'notify_card_created_email' => 'boolean',
        'notify_card_created_whatsapp' => 'boolean',
        'notify_card_transaction_email' => 'boolean',
        'notify_card_transaction_whatsapp' => 'boolean',
        'admin_bot_paused' => 'boolean',
        'support_whatsapp_welcome_sent_at' => 'datetime',
    ];

    public function renter(): BelongsTo
    {
        return $this->belongsTo(Renter::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WhatsappWalletTransaction::class, 'whatsapp_wallet_id');
    }

    public function consumerApiAccount(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ConsumerWalletApiAccount::class, 'whatsapp_wallet_id');
    }

    public function inactiveReminders(): HasMany
    {
        return $this->hasMany(WhatsappWalletInactiveReminder::class, 'whatsapp_wallet_id');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeSearch($query, string $term)
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like, $term): void {
            $q->where('phone_e164', 'like', $like)
                ->orWhere('pay_code', 'like', $like)
                ->orWhere('sender_name', 'like', $like)
                ->orWhere('mevon_virtual_account_number', 'like', $like)
                ->orWhere('kyc_email', 'like', $like);

            if (is_numeric($term)) {
                $q->orWhere('id', (int) $term);
            }
        });
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Admin manual-chat mode: bot stays silent until user sends START BOT. */
    public function isAdminBotPaused(): bool
    {
        return (bool) ($this->admin_bot_paused ?? false);
    }

    public static function isAdminBotResumeCommand(string $commandToken): bool
    {
        return in_array($commandToken, ['START BOT', 'STARTBOT'], true);
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

    /** Best display name for P2P / history (sender name, else Tier 2 KYC name). */
    public function displayName(): ?string
    {
        $sender = $this->normalizedSenderName();
        if ($sender !== null) {
            return $sender;
        }

        $kyc = trim(trim((string) $this->kyc_fname).' '.trim((string) $this->kyc_lname));

        return $kyc !== '' ? $kyc : null;
    }

    /**
     * True until PIN and display name are set — show a short wallet menu and onboarding-style alerts.
     */
    public function needsQuickWalletSetup(): bool
    {
        return ! $this->hasPin() || $this->normalizedSenderName() === null;
    }

    public function isTier2(): bool
    {
        return (int) $this->tier >= self::TIER_RUBIES_VA;
    }

    public function mevonDebitAccountName(): string
    {
        $stored = trim((string) $this->mevon_account_name);
        if ($stored !== '') {
            return $stored;
        }

        return (string) ($this->displayName() ?? 'Wallet User');
    }

    public function canUseMevonPayoutApi(): bool
    {
        return $this->isTier2()
            && trim((string) $this->mevon_virtual_account_number) !== '';
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

    /**
     * Tier 2 only: when true, we email a 6-digit code for transfer confirmation (default false = secure link only).
     */
    public function wantsTransferEmailOtp(): bool
    {
        return $this->isTier2()
            && $this->transfer_email_otp_enabled
            && $this->resolveOtpEmail() !== null;
    }

    public function wantsCardCreatedEmail(): bool
    {
        return (bool) ($this->notify_card_created_email ?? true);
    }

    public function wantsCardCreatedWhatsapp(): bool
    {
        return (bool) ($this->notify_card_created_whatsapp ?? true);
    }

    public function wantsCardTransactionEmail(): bool
    {
        return (bool) ($this->notify_card_transaction_email ?? true);
    }

    public function wantsCardTransactionWhatsapp(): bool
    {
        return (bool) ($this->notify_card_transaction_whatsapp ?? true);
    }

    public function isPinLocked(): bool
    {
        return $this->pin_locked_until !== null && $this->pin_locked_until->isFuture();
    }

    public function tier1MaxBalance(): float
    {
        $fromSetting = Setting::get('whatsapp_wallet_tier1_max_balance');
        if ($fromSetting !== null && is_numeric($fromSetting)) {
            return (float) $fromSetting;
        }

        return (float) config('whatsapp.wallet.tier1_max_balance', 50000);
    }

    /** Tier 1 daily cap on money sent out (P2P, bank, VTU, etc.). Incoming top-ups do not count. */
    public function tier1DailyOutLimit(): float
    {
        $fromSetting = Setting::get('whatsapp_wallet_tier1_daily_transfer');
        if ($fromSetting !== null && is_numeric($fromSetting)) {
            return (float) $fromSetting;
        }

        return (float) config('whatsapp.wallet.tier1_daily_transfer_limit', 50000);
    }

    public function isTier1(): bool
    {
        return (int) $this->tier === self::TIER_WHATSAPP_ONLY;
    }

    /** Outbound send total for today (resets at calendar day). Not affected by received funds. */
    public function tier1DailyOutUsed(): float
    {
        if (! $this->isTier1()) {
            return 0.0;
        }

        $this->resetDailyTransferIfNeeded();

        return (float) $this->daily_transfer_total;
    }

    public function tier1DailyOutRemaining(): float
    {
        if (! $this->isTier1()) {
            return 0.0;
        }

        return max(0.0, $this->tier1DailyOutLimit() - $this->tier1DailyOutUsed());
    }

    public function tier1BalanceHeadroom(): float
    {
        if (! $this->isTier1()) {
            return 0.0;
        }

        return max(0.0, $this->tier1MaxBalance() - (float) $this->balance);
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

        if ($this->isTier1()) {
            $this->resetDailyTransferIfNeeded();
            if ($this->daily_transfer_total + $amount > $this->tier1DailyOutLimit() + 0.0001) {
                $remaining = max(0.0, $this->tier1DailyOutLimit() - (float) $this->daily_transfer_total);

                return [
                    'ok' => false,
                    'message' => 'Tier 1 daily *send* limit is ₦'.number_format($this->tier1DailyOutLimit(), 2).
                        ' (₦'.number_format($remaining, 2).' left today; received money does not count).'.
                        ' Try tomorrow or upgrade (*UPGRADE*).',
                ];
            }
        }

        return ['ok' => true];
    }
}

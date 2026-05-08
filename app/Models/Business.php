<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Support\Str;

class Business extends Authenticatable implements CanResetPasswordContract
{
    use HasFactory, SoftDeletes, Notifiable, CanResetPassword;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'website',
        'website_approved',
        'api_key',
        'webhook_url',
        'uses_external_account_numbers',
        'whatsapp_wallet_api_enabled',
        'email_account_id',
        'is_active',
        'rental_auto_approve',
        'balance',
        'overdraft_limit',
        'overdraft_approved_at',
        'overdraft_approved_by',
        'overdraft_interest_last_charged_at',
        'overdraft_status',
        'overdraft_requested_at',
        'overdraft_eligible',
        'overdraft_repayment_mode',
        'overdraft_application_notes',
        'overdraft_funding_source',
        'overdraft_approval_notes',
        'overdraft_repayment_started_at',
        'peer_lending_lend_eligible',
        'peer_lending_borrow_eligible',
        'peer_lending_lender_max_offer_amount',
        'peer_lending_lender_max_interest_percent',
        'peer_lending_lender_min_term_days',
        'peer_lending_lender_max_term_days',
        'peer_lending_lender_min_balance_reserve',
        'peer_lending_lender_conditions',
        'business_id',
        'email_verified_at',
        'profile_picture',
        'notifications_email_enabled',
        'notifications_payment_enabled',
        'notifications_withdrawal_enabled',
        'notifications_website_enabled',
        'notifications_security_enabled',
        'timezone',
        'currency',
        'auto_withdraw_threshold',
        'auto_withdraw_end_of_day',
        'two_factor_enabled',
        'two_factor_secret',
        'withdrawal_pin_hash',
        'withdrawal_pin_set_at',
        'charges_paid_by_customer',
        'charge_percentage',
        'charge_fixed',
        'charge_exempt',
        'account_number',
        'bank_code',
        'bank_name',
        'bank_address',
        'telegram_bot_token',
        'telegram_chat_id',
        'telegram_withdrawal_enabled',
        'telegram_security_enabled',
        'telegram_payment_enabled',
        'telegram_login_enabled',
        'telegram_admin_login_enabled',
        'daily_revenue',
        'monthly_revenue',
        'yearly_revenue',
        'last_daily_revenue_update',
        'last_monthly_revenue_update',
        'last_yearly_revenue_update',
        'rental_global_caution_fee_enabled',
        'rental_global_caution_fee_percent',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'withdrawal_pin_hash',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rental_auto_approve' => 'boolean',
        'website_approved' => 'boolean',
        'balance' => 'decimal:2',
        'overdraft_limit' => 'decimal:2',
        'overdraft_approved_at' => 'datetime',
        'overdraft_interest_last_charged_at' => 'datetime',
        'overdraft_requested_at' => 'datetime',
        'overdraft_eligible' => 'boolean',
        'overdraft_repayment_started_at' => 'datetime',
        'peer_lending_lend_eligible' => 'boolean',
        'peer_lending_borrow_eligible' => 'boolean',
        'peer_lending_lender_max_offer_amount' => 'decimal:2',
        'peer_lending_lender_max_interest_percent' => 'decimal:4',
        'peer_lending_lender_min_term_days' => 'integer',
        'peer_lending_lender_max_term_days' => 'integer',
        'peer_lending_lender_min_balance_reserve' => 'decimal:2',
        'password' => 'hashed',
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'notifications_email_enabled' => 'boolean',
        'notifications_payment_enabled' => 'boolean',
        'notifications_withdrawal_enabled' => 'boolean',
        'notifications_website_enabled' => 'boolean',
        'notifications_security_enabled' => 'boolean',
        'telegram_withdrawal_enabled' => 'boolean',
        'telegram_security_enabled' => 'boolean',
        'telegram_payment_enabled' => 'boolean',
        'telegram_login_enabled' => 'boolean',
        'telegram_admin_login_enabled' => 'boolean',
        'daily_revenue' => 'decimal:2',
        'monthly_revenue' => 'decimal:2',
        'yearly_revenue' => 'decimal:2',
        'last_daily_revenue_update' => 'datetime',
        'last_monthly_revenue_update' => 'datetime',
        'last_yearly_revenue_update' => 'datetime',
        'rental_global_caution_fee_enabled' => 'boolean',
        'rental_global_caution_fee_percent' => 'decimal:2',
        'two_factor_enabled' => 'boolean',
        'withdrawal_pin_set_at' => 'datetime',
        'auto_withdraw_threshold' => 'decimal:2',
        'auto_withdraw_end_of_day' => 'boolean',
        'charges_paid_by_customer' => 'boolean',
        'charge_percentage' => 'decimal:2',
        'charge_fixed' => 'decimal:2',
        'charge_exempt' => 'boolean',
        'uses_external_account_numbers' => 'boolean',
        'whatsapp_wallet_api_enabled' => 'boolean',
    ];

    /**
     * Generate API key for business
     */
    public static function generateApiKey(): string
    {
        return 'pk_' . Str::random(32);
    }

    /**
     * Generate a unique 5-character business ID
     */
    public static function generateBusinessId(): string
    {
        do {
            // Generate 5 random alphanumeric characters (uppercase letters and numbers)
            $businessId = strtoupper(Str::random(5));
            // Ensure it contains both letters and numbers
            if (!preg_match('/[A-Z]/', $businessId) || !preg_match('/[0-9]/', $businessId)) {
                // If it doesn't have both, regenerate
                continue;
            }
        } while (self::where('business_id', $businessId)->exists());

        return $businessId;
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($business) {
            if (empty($business->api_key)) {
                $business->api_key = self::generateApiKey();
            }
            if (empty($business->business_id)) {
                $business->business_id = self::generateBusinessId();
            }
        });

        static::created(function ($business) {
            \App\Models\User::where('email', $business->email)->whereNull('business_id')->update(['business_id' => $business->id]);
        });

        static::updated(function (Business $business) {
            if (! $business->wasChanged('balance')) {
                return;
            }

            $previousBalance = (float) $business->getOriginal('balance');
            $newBalance = (float) $business->balance;

            if ($business->hasOverdraftApproved()
                && $business->overdraft_funding_source === \App\Services\Credit\OverdraftFundingService::FUNDING_CAPITAL_RESERVE) {
                app(\App\Services\Credit\OverdraftFundingService::class)
                    ->syncOnBalanceChange($business, $previousBalance, $newBalance);
            }

            if (! $business->hasOverdraftApproved()) {
                return;
            }
            if ($business->overdraft_repayment_mode !== \App\Services\Credit\OverdraftInstallmentService::MODE_SPLIT_30D) {
                return;
            }
            if ($newBalance >= 0) {
                app(\App\Services\Credit\OverdraftInstallmentService::class)->syncInstallmentStatuses($business->fresh());

                return;
            }
            if ($business->overdraft_repayment_started_at) {
                app(\App\Services\Credit\OverdraftInstallmentService::class)->syncInstallmentStatuses($business->fresh());

                return;
            }
            app(\App\Services\Credit\OverdraftInstallmentService::class)->startCycle($business->fresh());
        });
    }

    /**
     * User linked to this business (same email).
     */
    public function user()
    {
        return $this->hasOne(User::class, 'business_id');
    }

    /**
     * Get account numbers for this business
     */
    public function accountNumbers()
    {
        return $this->hasMany(AccountNumber::class);
    }

    /**
     * Get payments for this business
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get withdrawal requests for this business
     */
    public function withdrawalRequests()
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function overdraftInstallments()
    {
        return $this->hasMany(BusinessOverdraftInstallment::class);
    }

    public function lendingOffers()
    {
        return $this->hasMany(BusinessLendingOffer::class, 'lender_business_id');
    }

    public function loansAsBorrower()
    {
        return $this->hasMany(BusinessLoan::class, 'borrower_business_id');
    }

    /**
     * Get saved withdrawal accounts for this business
     */
    public function withdrawalAccounts()
    {
        return $this->hasMany(BusinessWithdrawalAccount::class);
    }

    /**
     * Get default withdrawal account
     */
    public function defaultWithdrawalAccount()
    {
        return $this->withdrawalAccounts()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Get primary account number
     */
    public function primaryAccountNumber()
    {
        return $this->accountNumbers()
            ->where('is_active', true)
            ->where('is_pool', false)
            ->where('is_external', false)
            ->first();
    }

    /**
     * Check if business has account number
     */
    public function hasAccountNumber(): bool
    {
        return $this->accountNumbers()
            ->where('is_active', true)
            ->where('is_pool', false)
            ->where('is_external', false)
            ->exists();
    }

    /**
     * Get email account for this business
     */
    public function emailAccount()
    {
        return $this->belongsTo(EmailAccount::class);
    }

    /**
     * Get the email address that should be used for password reset
     */
    public function getEmailForPasswordReset()
    {
        return $this->email;
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\BusinessResetPasswordNotification($token));
    }

    /**
     * Get verifications for this business
     */
    public function verifications()
    {
        return $this->hasMany(BusinessVerification::class);
    }

    /**
     * Check if all required KYC documents are submitted
     */
    public function hasAllRequiredKycDocuments(): bool
    {
        $requiredTypes = BusinessVerification::getRequiredTypes();
        $submittedTypes = $this->verifications()
            ->whereIn('verification_type', $requiredTypes)
            ->pluck('verification_type')
            ->unique()
            ->toArray();

        return count($submittedTypes) === count($requiredTypes);
    }

    /**
     * Get missing required KYC documents
     */
    public function getMissingKycDocuments(): array
    {
        $requiredTypes = BusinessVerification::getRequiredTypes();
        $submittedTypes = $this->verifications()
            ->whereIn('verification_type', $requiredTypes)
            ->pluck('verification_type')
            ->unique()
            ->toArray();

        return array_diff($requiredTypes, $submittedTypes);
    }

    /**
     * Check if all required KYC documents are approved
     */
    public function hasAllKycDocumentsApproved(): bool
    {
        $requiredTypes = BusinessVerification::getRequiredTypes();
        $approvedCount = $this->verifications()
            ->whereIn('verification_type', $requiredTypes)
            ->where('status', BusinessVerification::STATUS_APPROVED)
            ->count();

        return $approvedCount === count($requiredTypes);
    }

    /**
     * Get activity logs for this business
     */
    public function activityLogs()
    {
        return $this->hasMany(BusinessActivityLog::class);
    }

    /**
     * Get notifications for this business
     */
    public function notifications()
    {
        return $this->hasMany(BusinessNotification::class);
    }

    /**
     * Get unread notifications
     */
    public function unreadNotifications()
    {
        return $this->notifications()->where('is_read', false);
    }

    /**
     * Get support tickets for this business
     */
    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Get websites for this business
     */
    public function websites()
    {
        return $this->hasMany(BusinessWebsite::class);
    }

    public function externalApis()
    {
        return $this->belongsToMany(ExternalApi::class, 'business_external_api')
            ->withPivot(['assignment_mode', 'services', 'va_generation_mode'])
            ->withTimestamps();
    }

    public function usesExternalProvider(string $providerKey): bool
    {
        if (!$this->relationLoaded('externalApis')) {
            $this->load('externalApis');
        }

        return $this->externalApis
            ->where('provider_key', $providerKey)
            ->where('is_active', true)
            ->isNotEmpty();
    }

    public function getExternalProviderAssignment(string $providerKey): ?ExternalApi
    {
        if (!$this->relationLoaded('externalApis')) {
            $this->load('externalApis');
        }

        return $this->externalApis
            ->first(function ($api) use ($providerKey) {
                return $api->provider_key === $providerKey && $api->is_active;
            });
    }

    public function externalProviderModeForService(string $providerKey, ?string $service = null): string
    {
        $assignment = $this->getExternalProviderAssignment($providerKey);
        if (!$assignment) {
            return 'internal_only';
        }

        $mode = $assignment->pivot->assignment_mode ?? 'hybrid';
        $services = $assignment->pivot->services;
        $services = is_array($services) ? $services : (is_string($services) ? json_decode($services, true) : null);
        $services = is_array($services) ? array_values(array_filter($services)) : [];

        if (!empty($services) && $service !== null && !in_array($service, $services, true)) {
            return 'internal_only';
        }

        return in_array($mode, ['external_only', 'hybrid', 'internal_only'], true) ? $mode : 'hybrid';
    }

    /**
     * Get external VA generation mode for a given provider.
     * Used for providers like MEVONPAY to decide between createdynamic vs createtempva.
     */
    public function externalProviderVaGenerationMode(string $providerKey): string
    {
        $assignment = $this->getExternalProviderAssignment($providerKey);
        if (!$assignment) {
            return 'dynamic';
        }

        $mode = strtolower(trim((string) ($assignment->pivot->va_generation_mode ?? 'dynamic')));
        if (!in_array($mode, ['dynamic', 'temp'], true)) {
            return 'dynamic';
        }

        return $mode;
    }

    /**
     * Get approved websites for this business
     */
    public function approvedWebsites()
    {
        return $this->hasMany(BusinessWebsite::class)->where('is_approved', true);
    }

    /**
     * True when $url host matches an approved business website host (same rules as Pay API webhook validation).
     */
    public function webhookUrlMatchesApprovedWebsites(string $url): bool
    {
        $parsedUrl = parse_url(trim($url));
        $urlHost = $parsedUrl['host'] ?? null;
        if (! $urlHost) {
            return false;
        }

        $urlHost = strtolower(preg_replace('/^www\./', '', $urlHost));

        foreach ($this->approvedWebsites as $website) {
            $websiteUrl = $website->website_url;
            $websiteHost = parse_url($websiteUrl, PHP_URL_HOST);
            if (! $websiteHost) {
                $websiteHost = preg_replace('#^https?://#', '', $websiteUrl);
                $websiteHost = preg_replace('#/.*$#', '', $websiteHost);
            }
            if (! $websiteHost) {
                continue;
            }
            $websiteHost = strtolower(preg_replace('/^www\./', '', $websiteHost));
            if ($urlHost === $websiteHost) {
                return true;
            }
            $urlParts = explode('.', $urlHost);
            $websiteParts = explode('.', $websiteHost);
            if (count($urlParts) >= count($websiteParts)) {
                $urlSuffix = implode('.', array_slice($urlParts, -count($websiteParts)));
                if ($urlSuffix === $websiteHost) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Partner wallet pay: full webhook URL must match business or an approved website saved webhook (normalized), or host-only match.
     */
    public function incomingPartnerWebhookUrlIsAllowed(string $url): bool
    {
        $normalized = rtrim(trim($url), '/');
        if ($normalized === '') {
            return false;
        }

        $businessHook = $this->webhook_url ? rtrim(trim((string) $this->webhook_url), '/') : '';
        if ($businessHook !== '' && hash_equals($businessHook, $normalized)) {
            return true;
        }

        foreach ($this->approvedWebsites()->whereNotNull('webhook_url')->where('webhook_url', '!=', '')->get() as $site) {
            $saved = rtrim(trim((string) $site->webhook_url), '/');
            if ($saved !== '' && hash_equals($saved, $normalized)) {
                return true;
            }
        }

        return $this->webhookUrlMatchesApprovedWebsites($url);
    }

    /**
     * Check if business has any approved website
     */
    public function hasApprovedWebsite(): bool
    {
        return $this->approvedWebsites()->exists();
    }

    /**
     * Get primary website (for backward compatibility)
     * Returns the first approved website, or the first website if none approved
     */
    public function getPrimaryWebsiteAttribute()
    {
        return $this->approvedWebsites()->first() 
            ?? $this->websites()->first();
    }

    /**
     * Get the email address that should be used for verification.
     */
    public function getEmailForVerification()
    {
        return $this->email;
    }

    /**
     * Determine if the user has verified their email address.
     */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Mark the given user's email as verified.
     */
    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\BusinessEmailVerificationNotification());
    }

    /**
     * Check if email notifications are enabled
     */
    public function shouldReceiveEmailNotifications(): bool
    {
        return $this->notifications_email_enabled ?? true;
    }

    /**
     * Check if payment notifications are enabled
     */
    public function shouldReceivePaymentNotifications(): bool
    {
        return $this->notifications_payment_enabled ?? true;
    }

    /**
     * Check if withdrawal notifications are enabled
     */
    public function shouldReceiveWithdrawalNotifications(): bool
    {
        return $this->notifications_withdrawal_enabled ?? true;
    }

    /**
     * Check if website notifications are enabled
     */
    public function shouldReceiveWebsiteNotifications(): bool
    {
        return $this->notifications_website_enabled ?? true;
    }

    /**
     * Check if security notifications are enabled
     */
    public function shouldReceiveSecurityNotifications(): bool
    {
        return $this->notifications_security_enabled ?? true;
    }

    /**
     * Check if Telegram is configured
     */
    public function isTelegramConfigured(): bool
    {
        return !empty($this->telegram_bot_token) && !empty($this->telegram_chat_id);
    }

    /**
     * Get notification channels for the business
     */
    public function routeNotificationForTelegram(): ?string
    {
        return $this->isTelegramConfigured() ? 'telegram' : null;
    }

    /**
     * Generate 2FA secret
     */
    public function generateTwoFactorSecret(): string
    {
        $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);
        return $google2fa->generateSecretKey();
    }

    /**
     * Get 2FA QR Code URL
     */
    public function getTwoFactorQrCodeUrl(): string
    {
        $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);
        $appName = \App\Models\Setting::get('site_name', 'CheckoutPay');
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            $appName,
            $this->email,
            $this->two_factor_secret
        );
        return $qrCodeUrl;
    }

    /**
     * Verify 2FA code
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        if (!$this->two_factor_enabled || !$this->two_factor_secret) {
            return false;
        }

        $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);
        return $google2fa->verifyKey($this->two_factor_secret, $code);
    }

    /**
     * Check if auto-withdrawal should be triggered
     */
    /**
     * Whether overdraft is approved and has a limit.
     */
    public function hasOverdraftApproved(): bool
    {
        return $this->overdraft_approved_at !== null
            && (float) $this->overdraft_limit > 0;
    }

    public function canApplyForOverdraft(): bool
    {
        if (! $this->overdraft_eligible || ! $this->is_active) {
            return false;
        }
        if ($this->hasOverdraftApproved()) {
            return false;
        }
        if ($this->overdraft_status === 'pending') {
            return false;
        }

        return true;
    }

    /**
     * Maximum principal this lender may post on an offer (balance minus reserve, capped by admin max if set).
     */
    public function peerLendingMaxOfferAmountAllowed(): float
    {
        $balance = (float) $this->balance;
        $reserve = (float) ($this->peer_lending_lender_min_balance_reserve ?? 0);
        $fromBalance = max(0.0, round($balance - $reserve, 2));
        $adminCap = $this->peer_lending_lender_max_offer_amount;
        if ($adminCap !== null && (float) $adminCap > 0) {
            return min($fromBalance, (float) $adminCap);
        }

        return $fromBalance;
    }

    public function peerLendingMinTermDaysForOffers(): int
    {
        return max(1, (int) ($this->peer_lending_lender_min_term_days ?? 7));
    }

    public function peerLendingMaxTermDaysForOffers(): int
    {
        return min(730, max($this->peerLendingMinTermDaysForOffers(), (int) ($this->peer_lending_lender_max_term_days ?? 730)));
    }

    public function peerLendingMaxInterestPercentForOffers(): float
    {
        if ($this->peer_lending_lender_max_interest_percent !== null) {
            return min(100.0, max(0.0, (float) $this->peer_lending_lender_max_interest_percent));
        }

        return 100.0;
    }

    /**
     * @return array{max_amount: float, min_term: int, max_term: int, max_interest: float, reserve: float, conditions: ?string}
     */
    public function peerLendingLenderRulesSummary(): array
    {
        return [
            'max_amount' => $this->peerLendingMaxOfferAmountAllowed(),
            'min_term' => $this->peerLendingMinTermDaysForOffers(),
            'max_term' => $this->peerLendingMaxTermDaysForOffers(),
            'max_interest' => $this->peerLendingMaxInterestPercentForOffers(),
            'reserve' => (float) ($this->peer_lending_lender_min_balance_reserve ?? 0),
            'conditions' => $this->peer_lending_lender_conditions,
        ];
    }

    /**
     * Ledger balance = current book balance (can be negative with overdraft).
     */
    public function getLedgerBalance(): float
    {
        return (float) $this->balance;
    }

    /**
     * Available to withdraw: balance + overdraft limit when overdraft is approved.
     */
    public function getAvailableBalance(): float
    {
        $balance = (float) $this->balance;
        if ($this->hasOverdraftApproved()) {
            return $balance + (float) $this->overdraft_limit;
        }
        return $balance;
    }

    public function shouldTriggerAutoWithdrawal(): bool
    {
        if (!$this->auto_withdraw_threshold || $this->auto_withdraw_threshold <= 0) {
            return false;
        }

        return $this->balance >= $this->auto_withdraw_threshold;
    }

    /**
     * Get default withdrawal details (from last approved withdrawal or saved withdrawal account)
     */
    public function getDefaultWithdrawalDetails(): ?array
    {
        $lastWithdrawal = $this->withdrawalRequests()
            ->where('status', 'approved')
            ->latest()
            ->first();

        if ($lastWithdrawal) {
            return [
                'bank_name' => $lastWithdrawal->bank_name,
                'account_number' => $lastWithdrawal->account_number,
                'account_name' => $lastWithdrawal->account_name,
            ];
        }

        // Fall back to default saved withdrawal account (required for auto-withdraw when no past withdrawal)
        $saved = $this->defaultWithdrawalAccount()
            ?? $this->withdrawalAccounts()->where('is_active', true)->first();

        if ($saved) {
            return [
                'bank_name' => $saved->bank_name,
                'account_number' => $saved->account_number,
                'account_name' => $saved->account_name,
            ];
        }

        return null;
    }

    /**
     * Whether the business has at least one saved withdrawal account (for auto-withdrawal)
     */
    public function hasSavedWithdrawalAccount(): bool
    {
        return $this->withdrawalAccounts()->where('is_active', true)->exists();
    }

    /**
     * Trigger auto-withdrawal if threshold is reached.
     * When auto_withdraw_end_of_day is true, only the daily 5pm scheduler should trigger (not on payment).
     */
    public function triggerAutoWithdrawal(bool $fromEndOfDayScheduler = false): ?\App\Models\WithdrawalRequest
    {
        if (!$this->shouldTriggerAutoWithdrawal()) {
            return null;
        }

        // If business chose "end of day" (5pm), only run from the scheduled job, not on payment
        if ($this->auto_withdraw_end_of_day && !$fromEndOfDayScheduler) {
            return null;
        }

        // Check if there's already a pending withdrawal
        $pendingWithdrawal = $this->withdrawalRequests()
            ->where('status', 'pending')
            ->first();

        if ($pendingWithdrawal) {
            return null; // Already has pending withdrawal
        }

        // Get default withdrawal details
        $details = $this->getDefaultWithdrawalDetails();

        if (!$details) {
            // No withdrawal details available, cannot auto-withdraw
            \Log::warning('Auto-withdrawal triggered but no withdrawal details found', [
                'business_id' => $this->id,
                'balance' => $this->balance,
                'threshold' => $this->auto_withdraw_threshold,
            ]);
            return null;
        }

        // Create withdrawal request for full balance (or threshold amount)
        $amount = min($this->balance, $this->auto_withdraw_threshold * 10); // Cap at 10x threshold to prevent issues

        $withdrawal = $this->withdrawalRequests()->create([
            'amount' => $amount,
            'bank_name' => $details['bank_name'],
            'account_number' => $details['account_number'],
            'account_name' => $details['account_name'],
            'notes' => 'Auto-withdrawal triggered - Balance reached threshold of ₦' . number_format($this->auto_withdraw_threshold, 2),
            'status' => 'pending',
        ]);

        // Send notification to business
        $this->notify(new \App\Notifications\WithdrawalRequestedNotification($withdrawal));

        // Notify admin (Telegram + email) so they can treat withdrawal ASAP
        app(\App\Services\AdminWithdrawalAlertService::class)->send($withdrawal);

        \Log::info('Auto-withdrawal triggered', [
            'business_id' => $this->id,
            'withdrawal_id' => $withdrawal->id,
            'amount' => $amount,
            'balance' => $this->balance,
            'threshold' => $this->auto_withdraw_threshold,
        ]);

        return $withdrawal;
    }

    /**
     * Increment balance with charges applied
     *
     * @param float $amount Original payment amount (or received amount for mismatches)
     * @param Payment|null $payment Payment model (optional, for storing charge details)
     * @param float|null $receivedAmount If provided, use this instead of amount for charge calculation
     * @return float Amount actually added to balance
     */
    public function incrementBalanceWithCharges(float $amount, ?Payment $payment = null, ?float $receivedAmount = null): float
    {
        $chargeService = app(\App\Services\ChargeService::class);
        
        // Use received amount if provided (for mismatches), otherwise use original amount
        $amountForCharges = $receivedAmount ?? $amount;
        
        // Use website-based charges if payment has website, fallback to business
        $website = $payment && $payment->relationLoaded('website') ? $payment->website : ($payment ? $payment->website()->first() : null);
        $charges = $chargeService->calculateCharges($amountForCharges, $website, $this);

        // Store charge details in payment if provided
        if ($payment) {
            $payment->update([
                'charge_percentage' => $charges['charge_percentage'],
                'charge_fixed' => $charges['charge_fixed'],
                'total_charges' => $charges['total_charges'],
                'business_receives' => $charges['business_receives'],
                'charges_paid_by_customer' => $charges['paid_by_customer'],
            ]);

            // Track charges collected for website if payment has a website
            if ($payment->business_website_id && $charges['total_charges'] > 0) {
                $paymentWebsite = $payment->website()->first();
                if ($paymentWebsite) {
                    $paymentWebsite->increment('total_charges_collected', $charges['total_charges']);
                }
            }
        }

        // Increment balance with the amount business receives (after charges)
        $this->increment('balance', $charges['business_receives']);

        // Record transaction and update revenue
        if ($payment) {
            $revenueService = app(\App\Services\RevenueService::class);
            $revenueService->recordTransaction($payment, $charges['business_receives']);
        }

        return $charges['business_receives'];
    }

    /**
     * Get all payments (transactions) for this business
     */
    public function transactions()
    {
        return $this->payments();
    }
}

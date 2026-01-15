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
        'email_account_id',
        'is_active',
        'balance',
        'business_id',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'website_approved' => 'boolean',
        'balance' => 'decimal:2',
        'password' => 'hashed',
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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

    /**
     * Get primary account number
     */
    public function primaryAccountNumber()
    {
        return $this->accountNumbers()
            ->where('is_active', true)
            ->where('is_pool', false)
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

    /**
     * Get approved websites for this business
     */
    public function approvedWebsites()
    {
        return $this->hasMany(BusinessWebsite::class)->where('is_approved', true);
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Business extends Authenticatable
{
    use HasFactory, SoftDeletes, Notifiable;

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
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($business) {
            if (empty($business->api_key)) {
                $business->api_key = self::generateApiKey();
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountNumber extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_number',
        'account_name',
        'bank_name',
        'business_id',
        'is_pool',
        'is_active',
        'usage_count',
    ];

    protected $casts = [
        'is_pool' => 'boolean',
        'is_active' => 'boolean',
        'usage_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the business that owns this account number
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get payments using this account number
     */
    public function payments()
    {
        return $this->hasMany(Payment::class, 'account_number', 'account_number');
    }

    /**
     * Scope for pool account numbers
     */
    public function scopePool($query)
    {
        return $query->where('is_pool', true);
    }

    /**
     * Scope for active account numbers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for business-specific account numbers
     */
    public function scopeBusinessSpecific($query)
    {
        return $query->where('is_pool', false);
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // When business_id becomes null, automatically move account number to pool
        static::updating(function ($accountNumber) {
            // If business_id is being set to null and it's currently a business-specific account
            if (is_null($accountNumber->business_id) && !$accountNumber->is_pool && $accountNumber->isDirty('business_id')) {
                $accountNumber->is_pool = true;
            }
        });

        // Also handle when business is deleted (via foreign key cascade sets business_id to null)
        // Use updating event to catch when business_id changes to null from database trigger
        static::saving(function ($accountNumber) {
            // If business_id is null but is_pool is false, move to pool
            // This handles cases where business_id was set to null by foreign key cascade
            if (is_null($accountNumber->business_id) && !$accountNumber->is_pool) {
                $accountNumber->is_pool = true;
            }
        });

        // Invalidate cache when account numbers are created or updated
        static::created(function () {
            app(\App\Services\AccountNumberService::class)->invalidatePendingAccountsCache();
        });

        static::updated(function ($accountNumber) {
            // Invalidate cache if pool status or active status changed
            if ($accountNumber->isDirty(['is_pool', 'is_active', 'business_id'])) {
                app(\App\Services\AccountNumberService::class)->invalidatePendingAccountsCache();
            }
        });
    }

    /**
     * Scope for account numbers that should be in pool (business_id is null but is_pool is false)
     */
    public function scopeShouldBeInPool($query)
    {
        return $query->whereNull('business_id')
            ->where('is_pool', false)
            ->where('is_active', true);
    }
}

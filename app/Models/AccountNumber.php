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
}

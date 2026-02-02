<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessWithdrawalAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'account_number',
        'account_name',
        'bank_name',
        'bank_code',
        'is_default',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the business that owns this withdrawal account
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Mark as used (update last_used_at)
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Set as default account (unset others first)
     */
    public function setAsDefault(): void
    {
        // Unset other default accounts for this business
        static::where('business_id', $this->business_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);
        
        $this->update(['is_default' => true]);
    }
}

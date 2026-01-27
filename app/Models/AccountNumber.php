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
    ];

    /**
     * Relationships
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Scopes
     */
    public function scopePool($query)
    {
        return $query->where('is_pool', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

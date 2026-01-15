<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessWebsite extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'website_url',
        'is_approved',
        'notes',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the business that owns this website
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the admin who approved this website
     */
    public function approver()
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    /**
     * Scope to get only approved websites
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope to get only pending websites
     */
    public function scopePending($query)
    {
        return $query->where('is_approved', false);
    }
}

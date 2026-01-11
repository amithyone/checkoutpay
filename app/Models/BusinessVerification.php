<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessVerification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'verification_type',
        'status',
        'document_type',
        'document_path',
        'rejection_reason',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    const TYPE_BASIC = 'basic';
    const TYPE_BUSINESS_REGISTRATION = 'business_registration';
    const TYPE_BANK_ACCOUNT = 'bank_account';
    const TYPE_IDENTITY = 'identity';
    const TYPE_ADDRESS = 'address';

    /**
     * Get the business that owns this verification
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the admin who reviewed this verification
     */
    public function reviewer()
    {
        return $this->belongsTo(\App\Models\Admin::class, 'reviewed_by');
    }

    /**
     * Check if verification is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if verification is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING || $this->status === self::STATUS_UNDER_REVIEW;
    }
}

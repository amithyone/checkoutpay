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
        'provider_verified_at',
        'provider_verified_by',
        'provider_verified_name',
        'provider_verify_reference',
        'provider_verify_status',
        'provider_verify_message',
        'provider_verify_payload',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'provider_verified_at' => 'datetime',
        'provider_verify_payload' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    const PROVIDER_VERIFY_PASSED = 'passed';
    const PROVIDER_VERIFY_FAILED = 'failed';

    const TYPE_BASIC = 'basic';
    const TYPE_BUSINESS_REGISTRATION = 'business_registration';
    const TYPE_BANK_ACCOUNT = 'bank_account';
    const TYPE_IDENTITY = 'identity';
    const TYPE_ADDRESS = 'address';
    const TYPE_BVN = 'bvn';
    const TYPE_NIN = 'nin';
    const TYPE_CAC_CERTIFICATE = 'cac_certificate';
    const TYPE_CAC_APPLICATION = 'cac_application';
    const TYPE_ACCOUNT_NUMBER = 'account_number';
    const TYPE_BANK_ADDRESS = 'bank_address';
    const TYPE_UTILITY_BILL = 'utility_bill';

    /**
     * Get all required verification types
     */
    public static function getRequiredTypes(): array
    {
        return [
            self::TYPE_BVN,
            self::TYPE_NIN,
            self::TYPE_CAC_CERTIFICATE,
            self::TYPE_CAC_APPLICATION,
            self::TYPE_ACCOUNT_NUMBER,
            self::TYPE_UTILITY_BILL,
        ];
    }

    /**
     * Get verification type label
     */
    public static function getTypeLabel(string $type): string
    {
        return match($type) {
            self::TYPE_BVN => 'BVN (Bank Verification Number)',
            self::TYPE_NIN => 'NIN (National Identification Number)',
            self::TYPE_CAC_CERTIFICATE => 'CAC Certificate',
            self::TYPE_CAC_APPLICATION => 'CAC Application',
            self::TYPE_ACCOUNT_NUMBER => 'Business Bank Account',
            self::TYPE_BANK_ADDRESS => 'Bank Address',
            self::TYPE_UTILITY_BILL => 'Utility Bill',
            self::TYPE_BASIC => 'Basic Information',
            self::TYPE_BUSINESS_REGISTRATION => 'Business Registration',
            self::TYPE_BANK_ACCOUNT => 'Bank Account',
            self::TYPE_IDENTITY => 'Identity Document',
            self::TYPE_ADDRESS => 'Address Verification',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

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

    public static function countPendingReview(): int
    {
        return static::query()
            ->whereIn('verification_type', self::getRequiredTypes())
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_UNDER_REVIEW])
            ->count();
    }

    public function isTextBased(): bool
    {
        return in_array($this->verification_type, [
            self::TYPE_ACCOUNT_NUMBER,
            self::TYPE_BVN,
            self::TYPE_NIN,
        ], true);
    }

    public function requiresMevonVerification(): bool
    {
        return in_array($this->verification_type, [self::TYPE_BVN, self::TYPE_NIN], true);
    }

    public function isProviderVerified(): bool
    {
        return $this->provider_verify_status === self::PROVIDER_VERIFY_PASSED
            && $this->provider_verified_at !== null;
    }

    public function extractSubmittedIdentityNumber(): ?string
    {
        if (! $this->requiresMevonVerification()) {
            return null;
        }

        $text = (string) $this->document_type;
        if (preg_match('/(\d{11})/', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function providerVerifier()
    {
        return $this->belongsTo(\App\Models\Admin::class, 'provider_verified_by');
    }
}

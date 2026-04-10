<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Renter extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable, SoftDeletes;

    const KYC_ID_STATUS_PENDING = 'pending';

    const KYC_ID_STATUS_APPROVED = 'approved';

    const KYC_ID_STATUS_REJECTED = 'rejected';

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\RenterEmailVerificationNotification);
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'wallet_balance',
        'verified_account_number',
        'verified_account_name',
        'verified_bank_name',
        'verified_bank_code',
        'rubies_account_number',
        'rubies_account_name',
        'rubies_bank_name',
        'rubies_bank_code',
        'rubies_reference',
        'rubies_account_created_at',
        'bvn',
        'age',
        'instagram_url',
        'kyc_verified_at',
        'kyc_id_card_path',
        'kyc_id_type',
        'kyc_id_front_path',
        'kyc_id_back_path',
        'kyc_id_status',
        'kyc_id_reviewed_at',
        'kyc_id_reviewed_by',
        'kyc_id_rejection_reason',
        'is_active',
        'email_verified_at',
        'whatsapp_phone_e164',
        'whatsapp_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'kyc_verified_at' => 'datetime',
        'wallet_balance' => 'decimal:2',
        'age' => 'integer',
        'kyc_id_reviewed_at' => 'datetime',
        'rubies_account_created_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
        'whatsapp_verified_at' => 'datetime',
    ];

    /**
     * Get rentals for this renter
     */
    public function rentals()
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * Check if KYC is verified
     */
    public function isKycVerified(): bool
    {
        $bankOk = ! is_null($this->kyc_verified_at)
            && ! is_null($this->verified_account_number)
            && ! is_null($this->verified_account_name);

        $idOk = $this->kyc_id_status === self::KYC_ID_STATUS_APPROVED;

        return $bankOk && $idOk;
    }

    /**
     * Mark KYC as verified
     */
    public function markKycAsVerified(): bool
    {
        return $this->update(['kyc_verified_at' => now()]);
    }
}

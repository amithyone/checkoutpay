<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;

class Renter extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, SoftDeletes, Notifiable, MustVerifyEmailTrait;

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\RenterEmailVerificationNotification());
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'verified_account_number',
        'verified_account_name',
        'verified_bank_name',
        'verified_bank_code',
        'kyc_verified_at',
        'kyc_id_card_path',
        'is_active',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'kyc_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
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
        return !is_null($this->kyc_verified_at) && 
               !is_null($this->verified_account_number) && 
               !is_null($this->verified_account_name);
    }

    /**
     * Mark KYC as verified
     */
    public function markKycAsVerified(): bool
    {
        return $this->update(['kyc_verified_at' => now()]);
    }
}

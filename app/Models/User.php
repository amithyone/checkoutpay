<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'business_id',
        'name',
        'email',
        'password',
        'penalty_balance',
        'wallet_bal',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'penalty_balance' => 'decimal:2',
        'wallet_bal' => 'decimal:2',
    ];

    /**
     * Business linked to this user (when they have a business account).
     */
    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    /**
     * Whether the user has an associated business profile.
     */
    public function hasBusinessProfile(): bool
    {
        return $this->business_id !== null;
    }

    /**
     * Whether the user has a password set (vs social/login-only).
     */
    public function hasPassword(): bool
    {
        return ! empty($this->password);
    }
}

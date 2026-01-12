<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_ADMIN = 'admin';
    const ROLE_SUPPORT = 'support';
    const ROLE_STAFF = 'staff';

    /**
     * Get withdrawal requests processed by this admin
     */
    public function processedWithdrawals()
    {
        return $this->hasMany(WithdrawalRequest::class, 'processed_by');
    }

    /**
     * Check if admin is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Check if admin is staff
     */
    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    /**
     * Check if admin can manage account numbers
     */
    public function canManageAccountNumbers(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    /**
     * Check if admin can update business balances (super admin only)
     */
    public function canUpdateBusinessBalance(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Check if admin can manage settings
     */
    public function canManageSettings(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    /**
     * Check if admin can manage email accounts
     */
    public function canManageEmailAccounts(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    /**
     * Check if admin can review transactions
     */
    public function canReviewTransactions(): bool
    {
        return true; // All admins can review transactions
    }

    /**
     * Check if admin can manage support tickets
     */
    public function canManageSupportTickets(): bool
    {
        return true; // All admins can manage tickets
    }

    /**
     * Check if admin can test transactions
     */
    public function canTestTransactions(): bool
    {
        return true; // All admins can test transactions
    }

    /**
     * Check if admin can manage businesses
     */
    public function canManageBusinesses(): bool
    {
        return true; // All admins can manage businesses
    }

    /**
     * Check if admin can manage other admins/staff
     */
    public function canManageAdmins(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }
}

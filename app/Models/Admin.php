<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'sidebar_menu_order',
        'whatsapp_e164',
        'notify_wallet_signup',
        'admin_page_permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
        'sidebar_menu_order' => 'array',
        'notify_wallet_signup' => 'boolean',
        'admin_page_permissions' => 'array',
    ];

    const ROLE_SUPER_ADMIN = 'super_admin';

    const ROLE_ADMIN = 'admin';

    const ROLE_SUPPORT = 'support';

    const ROLE_STAFF = 'staff';

    const ROLE_TAX = 'tax';

    /** Wallet ops: view wallet tools, check payout/VTU status, push, reset passkey — no account/settings edits. */
    const ROLE_WALLET_SUPPORT = 'wallet_support';

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
     * NigTax-only admin (no access to payment gateway admin UI).
     */
    public function isTaxAdmin(): bool
    {
        return $this->role === self::ROLE_TAX;
    }

    /**
     * Wallet support ops role (CheckoutNow / WhatsApp wallet desk).
     */
    public function isWalletSupport(): bool
    {
        return $this->role === self::ROLE_WALLET_SUPPORT;
    }

    /**
     * Access wallet users, transactions, app sessions, card views, and related support actions.
     */
    public function canAccessWalletOps(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_WALLET_SUPPORT], true);
    }

    /**
     * Suspend wallets, link business, bot pause, wallet settings / FX, transfer-lock overrides.
     */
    public function canMutateWalletAccounts(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true);
    }

    /**
     * View virtual card requests (no mark-active / refund / rate trades).
     */
    public function canViewVirtualCards(): bool
    {
        return $this->canAccessWalletOps();
    }

    /**
     * Mutate virtual cards (status, refunds, rate tracker trades).
     */
    public function canManageVirtualCards(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true);
    }

    /**
     * Approve/reject business KYC (wallet_support is view-only).
     */
    public function canDecideBusinessKyc(): bool
    {
        if (! $this->is_active || $this->isTaxAdmin() || $this->isWalletSupport()) {
            return false;
        }

        return true;
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

    /**
     * Whether this admin may open the business dashboard as a merchant (impersonation).
     * Super admins, admins, support, and staff can; tax-only and inactive admins cannot.
     */
    public function canImpersonateBusiness(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isTaxAdmin() || $this->isWalletSupport()) {
            return false;
        }

        return true;
    }

    public function receivesWalletSignupAlerts(): bool
    {
        if (! $this->is_active || ! $this->isWalletSupport()) {
            return false;
        }

        return (bool) $this->notify_wallet_signup
            && is_string($this->whatsapp_e164)
            && trim($this->whatsapp_e164) !== '';
    }

    /**
     * @return list<string>
     */
    public function allowedPageKeys(): array
    {
        if ($this->isSuperAdmin() || $this->role === self::ROLE_ADMIN) {
            return array_keys(config('admin_pages.pages', []));
        }

        if (is_array($this->admin_page_permissions) && $this->admin_page_permissions !== []) {
            return array_values(array_filter($this->admin_page_permissions, 'is_string'));
        }

        $keys = [];
        foreach (config('admin_pages.pages', []) as $key => $def) {
            $roles = is_array($def['default_roles'] ?? null) ? $def['default_roles'] : [];
            if (in_array($this->role, $roles, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function canAccessPage(string $pageKey): bool
    {
        if ($this->isSuperAdmin() || $this->role === self::ROLE_ADMIN) {
            return true;
        }

        if ($this->isTaxAdmin()) {
            return false;
        }

        return in_array($pageKey, $this->allowedPageKeys(), true);
    }

    public function canAccessRoute(?string $routeName): bool
    {
        if ($routeName === null) {
            return true;
        }

        if ($this->isSuperAdmin() || $this->role === self::ROLE_ADMIN) {
            return true;
        }

        if ($this->isTaxAdmin()) {
            return false;
        }

        foreach (config('admin_pages.pages', []) as $key => $def) {
            $patterns = is_array($def['route_patterns'] ?? null) ? $def['route_patterns'] : [];
            foreach ($patterns as $pattern) {
                if (\Illuminate\Support\Str::is($pattern, $routeName)) {
                    return $this->canAccessPage($key);
                }
            }
        }

        $alwaysAllowed = config('admin_pages.always_allowed_routes', []);
        foreach ($alwaysAllowed as $pattern) {
            if (\Illuminate\Support\Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }
}

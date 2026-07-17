<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalsAdminAppSession extends Model
{
    public const LOGIN_PASSWORD = 'password';

    protected $fillable = [
        'session_uuid',
        'admin_id',
        'admin_email',
        'admin_name',
        'login_method',
        'platform',
        'app_version',
        'device_label',
        'ip_address',
        'user_agent',
        'personal_access_token_id',
        'started_at',
        'ended_at',
        'last_seen_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(RentalsAdminAppSessionEvent::class);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    public function loginMethodLabel(): string
    {
        return match ($this->login_method) {
            self::LOGIN_PASSWORD => 'Email & password',
            default => $this->login_method,
        };
    }
}

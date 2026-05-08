<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DesktopAppToken extends Model
{
    protected $fillable = [
        'name',
        'tenant_id',
        'bearer_token',
        'hmac_secret',
        'is_active',
        'last_seen_at',
        'admin_notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    protected $hidden = [
        'bearer_token',
        'hmac_secret',
    ];

    public static function generateSecret(int $bytes = 32): string
    {
        return Str::random($bytes * 2);
    }
}

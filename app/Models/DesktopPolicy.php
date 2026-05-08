<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesktopPolicy extends Model
{
    public const SCOPE_INSTANCE = 'instance';
    public const SCOPE_ROLE = 'role';
    public const SCOPE_GLOBAL = 'global';

    public const SCOPES = [
        self::SCOPE_INSTANCE,
        self::SCOPE_ROLE,
        self::SCOPE_GLOBAL,
    ];

    protected $fillable = [
        'tenant_id',
        'scope_type',
        'scope_value',
        'locked',
        'lock_reason_code',
        'lock_at',
        'grace_until',
        'min_heartbeat_seconds',
        'admin_notes',
    ];

    protected $casts = [
        'locked' => 'boolean',
        'lock_at' => 'datetime',
        'grace_until' => 'datetime',
        'min_heartbeat_seconds' => 'integer',
    ];

    public function toApiPayload(): array
    {
        return [
            'locked' => (bool) $this->locked,
            'lockReasonCode' => $this->lock_reason_code,
            'lockAt' => $this->lock_at?->toIso8601String(),
            'graceUntil' => $this->grace_until?->toIso8601String(),
            'minHeartbeatSeconds' => $this->min_heartbeat_seconds ?: 300,
        ];
    }
}

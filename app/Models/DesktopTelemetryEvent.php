<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DesktopTelemetryEvent extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'app_role',
        'app_instance_id',
        'event_id',
        'event_type',
        'event_ts',
        'app_version',
        'payload_json',
        'context_json',
        'received_at',
    ];

    protected $casts = [
        'event_ts' => 'datetime',
        'received_at' => 'datetime',
        'payload_json' => 'array',
        'context_json' => 'array',
    ];
}

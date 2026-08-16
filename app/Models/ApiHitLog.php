<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiHitLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'method',
        'path',
        'origin',
        'referer',
        'website_host',
        'ip',
        'user_agent',
        'business_id',
        'api_key_hint',
        'status_code',
        'successful',
        'message',
        'duration_ms',
        'created_at',
    ];

    protected $casts = [
        'successful' => 'boolean',
        'status_code' => 'integer',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}

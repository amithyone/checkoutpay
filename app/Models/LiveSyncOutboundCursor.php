<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveSyncOutboundCursor extends Model
{
    public const STATUS_BACKFILL = 'backfill';

    public const STATUS_CAUGHT_UP = 'caught_up';

    protected $primaryKey = 'entity';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'entity',
        'last_origin_id',
        'max_origin_id',
        'status',
        'last_run_at',
        'rows_pushed_total',
    ];

    protected $casts = [
        'last_origin_id' => 'integer',
        'max_origin_id' => 'integer',
        'rows_pushed_total' => 'integer',
        'last_run_at' => 'datetime',
    ];

    public function isCaughtUp(): bool
    {
        return $this->status === self::STATUS_CAUGHT_UP;
    }
}

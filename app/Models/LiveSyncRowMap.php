<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveSyncRowMap extends Model
{
    protected $fillable = [
        'entity',
        'origin_id',
        'local_id',
        'natural_key',
    ];
}

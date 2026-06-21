<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumerPasskeyCredential extends Model
{
    protected $fillable = [
        'consumer_trusted_device_id',
        'credential_id',
        'credential_record',
        'counter',
    ];

    protected $casts = [
        'credential_record' => 'array',
        'counter' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(ConsumerTrustedDevice::class, 'consumer_trusted_device_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NigtaxProPendingRegistration extends Model
{
    protected $fillable = [
        'payment_id',
        'email',
        'password_hash',
        'member_name',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}

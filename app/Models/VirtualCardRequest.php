<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualCardRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'whatsapp_wallet_id',
        'status',
        'fee_usd',
        'fee_ngn',
        'fx_rate_used',
        'external_reference',
        'card_external_id',
        'is_frozen',
        'last_operation_at',
        'last_operation_payload',
        'card_name',
        'home_number',
        'home_address',
        'request_payload',
        'response_payload',
        'failure_reason',
        'admin_notes',
        'activated_at',
        'handled_by_admin_id',
    ];

    protected $casts = [
        'fee_usd' => 'float',
        'fee_ngn' => 'float',
        'fx_rate_used' => 'float',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'activated_at' => 'datetime',
        'is_frozen' => 'boolean',
        'last_operation_at' => 'datetime',
        'last_operation_payload' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'handled_by_admin_id');
    }
}

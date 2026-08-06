<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CreditFacilityRequest extends Model
{
    public const KIND_OVERDRAFT = 'overdraft';

    public const KIND_LOAN = 'loan';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'public_id',
        'whatsapp_wallet_id',
        'business_id',
        'kind',
        'amount',
        'currency',
        'note',
        'status',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $row): void {
            if (trim((string) $row->public_id) === '') {
                $row->public_id = (string) Str::uuid();
            }
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return array{id: string, kind: string, status: string, amount: float, currency: string, note: ?string, created_at: ?string}
     */
    public function toApiArray(): array
    {
        return [
            'id' => (string) $this->public_id,
            'kind' => (string) $this->kind,
            'status' => (string) $this->status,
            'amount' => round((float) $this->amount, 2),
            'currency' => strtoupper((string) ($this->currency ?: 'NGN')),
            'note' => $this->note !== null && trim((string) $this->note) !== '' ? (string) $this->note : null,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}

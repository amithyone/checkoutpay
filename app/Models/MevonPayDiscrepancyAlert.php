<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MevonPayDiscrepancyAlert extends Model
{
    public const TRIGGER_SCHEDULED = 'scheduled';

    public const TRIGGER_LEDGER_ENTRY = 'ledger_entry';

    public const TRIGGER_MANUAL = 'manual';

    protected $fillable = [
        'checked_at',
        'expected_balance',
        'live_balance',
        'variance_amount',
        'tolerance',
        'ledger_entry_id',
        'trigger',
        'meta',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
        'expected_balance' => 'decimal:2',
        'live_balance' => 'decimal:2',
        'variance_amount' => 'decimal:2',
        'tolerance' => 'decimal:2',
        'meta' => 'array',
    ];

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(MevonPayLedgerEntry::class, 'ledger_entry_id');
    }

    public function triggerLabel(): string
    {
        return match ($this->trigger) {
            self::TRIGGER_SCHEDULED => 'Scheduled',
            self::TRIGGER_LEDGER_ENTRY => 'Ledger entry',
            self::TRIGGER_MANUAL => 'Manual',
            default => str_replace('_', ' ', (string) $this->trigger),
        };
    }
}

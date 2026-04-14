<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cross-border P2P FX: units of {@see $to_currency} per 1 {@see $from_currency}.
 */
class WhatsappCrossBorderFxRate extends Model
{
    protected $table = 'whatsapp_cross_border_fx_rates';

    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
    ];

    protected $casts = [
        'rate' => 'decimal:12',
    ];

    public function pairKey(): string
    {
        return strtoupper((string) $this->from_currency).'_'.strtoupper((string) $this->to_currency);
    }
}

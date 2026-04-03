<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NigtaxConsultantSetting extends Model
{
    protected $table = 'nigtax_consultant_settings';

    protected $fillable = [
        'default_consultant_id',
        'certified_fee_ngn',
        'is_enabled',
        'signatures_applied_count',
    ];

    protected $casts = [
        'certified_fee_ngn' => 'decimal:2',
        'is_enabled' => 'boolean',
        'signatures_applied_count' => 'integer',
    ];

    public function defaultConsultant(): BelongsTo
    {
        return $this->belongsTo(NigtaxConsultant::class, 'default_consultant_id');
    }

    public static function singleton(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'certified_fee_ngn' => 0,
                'is_enabled' => false,
                'signatures_applied_count' => 0,
            ]
        );
    }
}

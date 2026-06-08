<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MevonPayFxRateSnapshot extends Model
{
    protected $fillable = [
        'recorded_at',
        'mevon_mid',
        'published_mid',
        'sell_rate',
        'buy_rate',
        'source',
        'change_abs',
        'change_pct',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'mevon_mid' => 'float',
            'published_mid' => 'float',
            'sell_rate' => 'float',
            'buy_rate' => 'float',
            'change_abs' => 'float',
            'change_pct' => 'float',
        ];
    }
}

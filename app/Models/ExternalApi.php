<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalApi extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'provider_key',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function businesses()
    {
        return $this->belongsToMany(Business::class, 'business_external_api')
            ->withPivot(['assignment_mode', 'services', 'va_generation_mode'])
            ->withTimestamps();
    }
}

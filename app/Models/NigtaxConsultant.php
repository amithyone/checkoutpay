<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NigtaxConsultant extends Model
{
    protected $fillable = [
        'consultant_name',
        'firm_name',
        'title',
        'bio',
        'license_number',
        'contact_email',
        'signature_image_path',
        'stamp_image_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function certifiedOrders(): HasMany
    {
        return $this->hasMany(NigtaxCertifiedOrder::class, 'consultant_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteRevenueDaily extends Model
{
    use HasFactory;

    protected $table = 'website_revenue_daily';

    protected $fillable = [
        'business_website_id',
        'revenue_date',
        'revenue',
        'last_updated_at',
    ];

    protected $casts = [
        'revenue_date' => 'date',
        'revenue' => 'decimal:2',
        'last_updated_at' => 'datetime',
    ];

    /**
     * Get the website that owns this daily revenue record
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(BusinessWebsite::class, 'business_website_id');
    }
}

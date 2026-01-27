<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketType extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'quantity',
        'sold_quantity',
        'min_per_order',
        'max_per_order',
        'sales_start_date',
        'sales_end_date',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'sold_quantity' => 'integer',
        'min_per_order' => 'integer',
        'max_per_order' => 'integer',
        'sales_start_date' => 'datetime',
        'sales_end_date' => 'datetime',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Relationships
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function orderItems()
    {
        return $this->hasMany(TicketOrderItem::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if ticket type is available
     */
    public function isAvailable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->sold_quantity >= $this->quantity) {
            return false;
        }

        if ($this->sales_start_date && now() < $this->sales_start_date) {
            return false;
        }

        if ($this->sales_end_date && now() > $this->sales_end_date) {
            return false;
        }

        return true;
    }

    /**
     * Get available quantity
     */
    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->sold_quantity);
    }
}

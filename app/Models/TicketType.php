<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'quantity_available',
        'quantity_sold',
        'sales_start_date',
        'sales_end_date',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity_available' => 'integer',
        'quantity_sold' => 'integer',
        'sales_start_date' => 'datetime',
        'sales_end_date' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the event that owns this ticket type
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get all tickets of this type
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Check if ticket type is available for sale
     */
    public function isAvailable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->quantity_sold >= $this->quantity_available) {
            return false;
        }

        $now = now();
        
        if ($this->sales_start_date && $now < $this->sales_start_date) {
            return false;
        }

        if ($this->sales_end_date && $now > $this->sales_end_date) {
            return false;
        }

        return true;
    }

    /**
     * Get remaining quantity
     */
    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity_available - $this->quantity_sold);
    }

    /**
     * Check if sales have started
     */
    public function salesHaveStarted(): bool
    {
        if ($this->sales_start_date === null) {
            return true;
        }

        return now() >= $this->sales_start_date;
    }

    /**
     * Check if sales have ended
     */
    public function salesHaveEnded(): bool
    {
        if ($this->sales_end_date === null) {
            return false;
        }

        return now() > $this->sales_end_date;
    }

    /**
     * Scope for active ticket types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for available ticket types
     */
    public function scopeAvailable($query)
    {
        $now = now();
        return $query->where('is_active', true)
            ->whereColumn('quantity_sold', '<', 'quantity_available')
            ->where(function ($q) use ($now) {
                $q->whereNull('sales_start_date')
                  ->orWhere('sales_start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('sales_end_date')
                  ->orWhere('sales_end_date', '>', $now);
            });
    }
}

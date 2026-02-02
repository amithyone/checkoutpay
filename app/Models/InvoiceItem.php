<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'sort_order',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'total',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            // Auto-calculate total
            $item->total = $item->quantity * $item->unit_price;
        });

        static::saved(function ($item) {
            // Recalculate invoice totals
            $item->invoice->calculateTotals();
        });

        static::deleted(function ($item) {
            // Recalculate invoice totals
            $item->invoice->calculateTotals();
        });
    }

    /**
     * Get the invoice that owns this item
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}

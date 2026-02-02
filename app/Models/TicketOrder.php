<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TicketOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'business_id',
        'order_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'total_amount',
        'commission_amount',
        'payment_id',
        'coupon_id',
        'discount_amount',
        'payment_status',
        'status',
        'purchased_at',
        'refund_reason',
        'refunded_by',
        'refunded_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'purchased_at' => 'datetime',
        'refunded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    const PAYMENT_STATUS_PENDING = 'pending';
    const PAYMENT_STATUS_PAID = 'paid';
    const PAYMENT_STATUS_FAILED = 'failed';
    const PAYMENT_STATUS_REFUNDED = 'refunded';

    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = 'TKT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
            }
        });
    }

    /**
     * Get the event for this order
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the business that owns this order
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the payment associated with this order
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the coupon used for this order
     */
    public function coupon()
    {
        return $this->belongsTo(EventCoupon::class);
    }

    /**
     * Get all tickets in this order
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Get the admin who processed refund
     */
    public function refundedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'refunded_by');
    }

    /**
     * Check if order is paid
     */
    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PAID;
    }

    /**
     * Check if order is confirmed
     */
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Check if order can be refunded
     */
    public function canBeRefunded(): bool
    {
        if (!$this->isPaid() || !$this->isConfirmed()) {
            return false;
        }

        if ($this->payment_status === self::PAYMENT_STATUS_REFUNDED) {
            return false;
        }

        if (!$this->event->allow_refunds) {
            return false;
        }

        return true;
    }

    /**
     * Get ticket count
     */
    public function getTicketCountAttribute(): int
    {
        return $this->tickets()->count();
    }

    /**
     * Scope for paid orders
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_STATUS_PAID);
    }

    /**
     * Scope for confirmed orders
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }
}

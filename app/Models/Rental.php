<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Rental extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'renter_id',
        'business_id',
        'rental_number',
        'start_date',
        'end_date',
        'days',
        'daily_rate',
        'total_amount',
        'deposit_amount',
        'currency',
        'status',
        'verified_account_number',
        'verified_account_name',
        'verified_bank_name',
        'verified_bank_code',
        'renter_name',
        'renter_email',
        'renter_phone',
        'renter_address',
        'business_phone',
        'renter_notes',
        'business_notes',
        'approved_at',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'daily_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'days' => 'integer',
        'approved_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($rental) {
            if (empty($rental->rental_number)) {
                $rental->rental_number = self::generateRentalNumber();
            }
        });
    }

    /**
     * Generate unique rental number
     */
    public static function generateRentalNumber(): string
    {
        do {
            $number = 'RENT-' . date('Y') . '-' . strtoupper(Str::random(8));
        } while (self::where('rental_number', $number)->exists());

        return $number;
    }

    /**
     * Get the renter
     */
    public function renter()
    {
        return $this->belongsTo(Renter::class);
    }

    /**
     * Get the business
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get rental items
     */
    public function items()
    {
        return $this->belongsToMany(RentalItem::class, 'rental_rental_item')
            ->withPivot('quantity', 'unit_rate', 'total_amount')
            ->withTimestamps();
    }

    /**
     * Check if rental is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if rental is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Approve rental
     */
    public function approve(string $notes = null): bool
    {
        return $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_at' => now(),
            'business_notes' => $notes,
        ]);
    }

    /**
     * Reject rental
     */
    public function reject(string $notes = null): bool
    {
        return $this->update([
            'status' => self::STATUS_REJECTED,
            'business_notes' => $notes,
        ]);
    }
}

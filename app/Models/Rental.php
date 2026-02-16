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
        'returned_at',
        'penalty_amount',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'daily_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'days' => 'integer',
        'approved_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'returned_at' => 'datetime',
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

    /**
     * Return deadline (end of end_date).
     */
    public function returnDeadline(): \Carbon\Carbon
    {
        $date = $this->end_date instanceof \Carbon\Carbon
            ? $this->end_date
            : \Carbon\Carbon::parse($this->end_date);
        return $date->endOfDay();
    }

    /**
     * Whether the rental is overdue (past return deadline, not yet returned).
     */
    public function isOverdue(): bool
    {
        return ! $this->isReturned() && now()->isAfter($this->returnDeadline());
    }

    /**
     * Whether the rental has been returned.
     */
    public function isReturned(): bool
    {
        return $this->returned_at !== null;
    }

    /**
     * Return reminders sent for this rental.
     */
    public function returnReminders()
    {
        return $this->hasMany(RentalReturnReminder::class);
    }

    /**
     * Mark rental as returned and apply overdue penalty if applicable.
     * Call this when status is set to completed.
     */
    public function markAsReturned(): void
    {
        if ($this->returned_at) {
            return;
        }
        $this->update(['returned_at' => now()]);
        if (! $this->completed_at) {
            $this->update(['completed_at' => now()]);
        }

        $deadline = $this->returnDeadline();
        if (now()->lte($deadline)) {
            return;
        }

        $daysOverdue = (int) now()->diffInDays($deadline);
        $penaltyPerDay = (float) \App\Models\Setting::get('rental_penalty_per_day', 0);
        if ($penaltyPerDay <= 0) {
            $penaltyPerDay = (float) $this->daily_rate;
        }
        $penaltyAmount = round($daysOverdue * $penaltyPerDay, 2);
        if ($penaltyAmount <= 0) {
            return;
        }

        $this->update(['penalty_amount' => $penaltyAmount]);

        $user = \App\Models\User::where('email', $this->renter_email)->first();
        if (! $user) {
            return;
        }

        $user->increment('penalty_balance', $penaltyAmount);
        $user->decrement('wallet_bal', $penaltyAmount);

        \App\Models\UserWalletTransaction::create([
            'user_id' => $user->id,
            'type' => 'penalty',
            'amount' => -$penaltyAmount,
            'description' => 'Late return penalty: ' . $this->rental_number,
            'reference_type' => Rental::class,
            'reference_id' => $this->id,
        ]);
    }
}

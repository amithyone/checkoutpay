<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_id',
        'amount',
        'payer_name',
        'bank',
        'webhook_url',
        'account_number',
        'business_id',
        'status',
        'email_data',
        'matched_at',
        'expires_at',
        'received_amount',
        'is_mismatch',
        'mismatch_reason',
        'payer_account_number',
        'business_website_id',
        'charge_percentage',
        'charge_fixed',
        'total_charges',
        'business_receives',
        'charges_paid_by_customer',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'charge_percentage' => 'decimal:2',
        'charge_fixed' => 'decimal:2',
        'total_charges' => 'decimal:2',
        'business_receives' => 'decimal:2',
        'is_mismatch' => 'boolean',
        'charges_paid_by_customer' => 'boolean',
        'email_data' => 'array',
        'matched_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Relationships
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function accountNumberDetails()
    {
        return $this->belongsTo(AccountNumber::class, 'account_number', 'account_number');
    }

    public function ticketOrder()
    {
        return $this->hasOne(TicketOrder::class);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }
}

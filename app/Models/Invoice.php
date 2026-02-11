<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'logo',
        'invoice_number',
        'status',
        'client_name',
        'client_email',
        'client_phone',
        'client_address',
        'client_company',
        'client_tax_id',
        'invoice_date',
        'due_date',
        'currency',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'discount_type',
        'total_amount',
        'notes',
        'terms_and_conditions',
        'reference_number',
        'payment_link_code',
        'payment_id',
        'paid_at',
        'paid_amount',
        'sent_at',
        'viewed_at',
        'view_count',
        'email_sent_to_sender',
        'email_sent_to_receiver',
        'payment_email_sent_to_sender',
        'payment_email_sent_to_receiver',
        'allow_split_payment',
        'split_installments',
        'split_percentages',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'view_count' => 'integer',
        'email_sent_to_sender' => 'boolean',
        'email_sent_to_receiver' => 'boolean',
        'payment_email_sent_to_sender' => 'boolean',
        'payment_email_sent_to_receiver' => 'boolean',
        'allow_split_payment' => 'boolean',
        'split_installments' => 'integer',
        'split_percentages' => 'array',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = self::generateInvoiceNumber($invoice->business_id);
            }
            if (empty($invoice->payment_link_code)) {
                $invoice->payment_link_code = self::generatePaymentLinkCode();
            }
        });
    }

    /**
     * Generate unique invoice number
     */
    public static function generateInvoiceNumber($businessId): string
    {
        $business = Business::find($businessId);
        $prefix = $business ? strtoupper(substr($business->business_id, 0, 3)) : 'INV';
        
        do {
            $number = $prefix . '-' . date('Y') . '-' . strtoupper(Str::random(6));
        } while (self::where('invoice_number', $number)->exists());

        return $number;
    }

    /**
     * Generate unique payment link code
     */
    public static function generatePaymentLinkCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('payment_link_code', $code)->exists());

        return $code;
    }

    /**
     * Get the business that owns this invoice
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the invoice items
     */
    public function items()
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    /**
     * Get the primary payment (legacy single payment or first linked payment)
     */
    public function payment()
    {
        if (class_exists(\App\Models\Payment::class)) {
            return $this->belongsTo(\App\Models\Payment::class);
        }
        return null;
    }

    /**
     * Get all payments linked to this invoice (for split payments)
     */
    public function invoicePayments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    /**
     * Get all payments (via invoice_payments pivot) - for split payment
     */
    public function payments()
    {
        return $this->hasManyThrough(
            \App\Models\Payment::class,
            InvoicePayment::class,
            'invoice_id',
            'id',
            'id',
            'payment_id'
        );
    }

    /**
     * Total amount paid so far (from linked payments that are approved)
     */
    public function getPaidAmountTotalAttribute(): float
    {
        $fromPivot = (float) $this->invoicePayments()
            ->whereHas('payment', fn ($q) => $q->where('status', \App\Models\Payment::STATUS_APPROVED))
            ->sum('amount');
        if ($fromPivot > 0) {
            return $fromPivot;
        }
        return (float) ($this->paid_amount ?? 0);
    }

    /**
     * Check if invoice is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid' && $this->paid_at !== null;
    }

    /**
     * Check if invoice is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status !== 'paid' 
            && $this->status !== 'cancelled'
            && $this->due_date 
            && $this->due_date->isPast();
    }

    /**
     * Get payment link URL
     */
    public function getPaymentLinkUrlAttribute(): string
    {
        return route('invoices.pay', ['code' => $this->payment_link_code]);
    }

    /**
     * Get suggested split amounts by percentage (for display on payment page).
     * Returns array of ['percent' => 33.33, 'amount' => 206664.00] for each installment.
     */
    public function getSuggestedSplitAmounts(): array
    {
        if (!$this->allow_split_payment || empty($this->split_percentages) || !$this->total_amount) {
            return [];
        }
        $total = (float) $this->total_amount;
        $amounts = [];
        foreach ($this->split_percentages as $i => $pct) {
            $pct = (float) $pct;
            $amount = round($total * $pct / 100, 2);
            $amounts[] = ['percent' => $pct, 'amount' => $amount];
        }
        return $amounts;
    }

    /**
     * Calculate totals from items
     */
    public function calculateTotals(): void
    {
        $subtotal = $this->items->sum('total');
        
        // Calculate tax
        $taxAmount = ($subtotal * $this->tax_rate) / 100;
        
        // Calculate discount
        $discountAmount = 0;
        if ($this->discount_type === 'fixed') {
            $discountAmount = $this->discount_amount ?? 0;
        } elseif ($this->discount_type === 'percentage') {
            $discountAmount = ($subtotal * ($this->discount_amount ?? 0)) / 100;
        }
        
        // Calculate total
        $total = $subtotal + $taxAmount - $discountAmount;
        
        $this->subtotal = $subtotal;
        $this->tax_amount = $taxAmount;
        $this->total_amount = max(0, $total);
        $this->save();
    }
}

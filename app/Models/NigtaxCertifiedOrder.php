<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NigtaxCertifiedOrder extends Model
{
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'consultant_id',
        'customer_email',
        'customer_name',
        'report_type',
        'report_snapshot_json',
        'amount_paid',
        'payment_id',
        'transaction_id',
        'status',
        'paid_at',
        'signed_at',
        'delivered_at',
        'signed_pdf_path',
        'admin_notes',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'paid_at' => 'datetime',
        'signed_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(NigtaxConsultant::class, 'consultant_id');
    }
}

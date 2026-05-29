<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportIntakeSession extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_REJECTED_NON_PAYMENT = 'rejected_non_payment';

    public const STATUS_REJECTED_NOT_OUR_ACCOUNT = 'rejected_not_our_account';

    public const STATUS_LOCKED_OUT = 'locked_out';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'intake_token',
        'channel',
        'intake_status',
        'current_step',
        'issue_type',
        'is_payment_issue',
        'reported_destination_account',
        'reported_destination_bank',
        'reported_payee_name',
        'payment_session_id',
        'payment_amount_reported',
        'visitor_name',
        'payment_id',
        'account_on_session',
        'account_in_platform',
        'whatsapp_eligible_at',
        'payment_receipt_path',
        'link_whatsapp_wallet',
        'visitor_phone',
        'visitor_country',
        'whatsapp_wallet_id',
        'consumer_wallet_api_account_id',
        'support_ticket_id',
        'public_token',
        'bot_messages',
        'wrong_account_attempts',
        'locked_until',
        'last_visitor_ip',
    ];

    protected $casts = [
        'is_payment_issue' => 'boolean',
        'payment_amount_reported' => 'decimal:2',
        'account_on_session' => 'boolean',
        'account_in_platform' => 'boolean',
        'whatsapp_eligible_at' => 'datetime',
        'link_whatsapp_wallet' => 'boolean',
        'bot_messages' => 'array',
        'wrong_account_attempts' => 'integer',
        'locked_until' => 'datetime',
    ];

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function whatsappWallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }

    public function isWhatsappEligible(): bool
    {
        return $this->whatsapp_eligible_at !== null;
    }

    public function isLockedOut(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function isTerminal(): bool
    {
        if ($this->intake_status === self::STATUS_LOCKED_OUT && $this->isLockedOut()) {
            return true;
        }

        return in_array($this->intake_status, [
            self::STATUS_REJECTED_NON_PAYMENT,
            self::STATUS_COMPLETED,
        ], true);
    }

    public function canRetryDestinationAccount(): bool
    {
        return $this->current_step === 'destination_account'
            && $this->intake_status === self::STATUS_IN_PROGRESS
            && ! $this->isLockedOut();
    }
}

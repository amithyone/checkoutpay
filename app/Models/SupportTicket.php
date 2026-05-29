<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    use HasFactory, SoftDeletes;

    public const CHANNEL_CHECKOUT_WEB = 'checkout_web';

    public const CHANNEL_CHECKOUTNOW_APP = 'checkoutnow_app';

    public const CHANNEL_BUSINESS_DASHBOARD = 'business_dashboard';

    protected $fillable = [
        'channel',
        'issue_type',
        'payment_id',
        'payment_transaction_id',
        'payment_amount_reported',
        'business_id',
        'whatsapp_wallet_id',
        'wallet_linked',
        'visitor_country',
        'ticket_number',
        'subject',
        'message',
        'visitor_name',
        'visitor_email',
        'visitor_phone',
        'public_token',
        'wallet_onboarding_sent_at',
        'intake_status',
        'reported_destination_account',
        'reported_destination_bank',
        'reported_payee_name',
        'whatsapp_eligible_at',
        'payment_receipt_path',
        'account_on_session',
        'last_message_at',
        'admin_unread_count',
        'visitor_unread_count',
        'last_visitor_ip',
        'user_agent',
        'priority',
        'status',
        'assigned_to',
        'resolved_at',
    ];

    protected $casts = [
        'payment_amount_reported' => 'decimal:2',
        'resolved_at' => 'datetime',
        'wallet_onboarding_sent_at' => 'datetime',
        'whatsapp_eligible_at' => 'datetime',
        'account_on_session' => 'boolean',
        'last_message_at' => 'datetime',
        'wallet_linked' => 'boolean',
        'admin_unread_count' => 'integer',
        'visitor_unread_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    const STATUS_OPEN = 'open';

    const STATUS_IN_PROGRESS = 'in_progress';

    const STATUS_RESOLVED = 'resolved';

    const STATUS_CLOSED = 'closed';

    const PRIORITY_LOW = 'low';

    const PRIORITY_MEDIUM = 'medium';

    const PRIORITY_HIGH = 'high';

    const PRIORITY_URGENT = 'urgent';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = 'TICKET-'.strtoupper(Str::random(8));
            }
            if (empty($ticket->channel)) {
                $ticket->channel = self::CHANNEL_BUSINESS_DASHBOARD;
            }
        });
    }

    public static function publicChannels(): array
    {
        return [self::CHANNEL_CHECKOUT_WEB, self::CHANNEL_CHECKOUTNOW_APP];
    }

    public function isPublicChannel(): bool
    {
        return in_array($this->channel, self::publicChannels(), true);
    }

    public function displayName(): string
    {
        if ($this->visitor_name) {
            return $this->visitor_name;
        }
        if ($this->business) {
            return $this->business->name;
        }
        if ($this->visitor_phone) {
            return $this->visitor_phone;
        }

        return 'Visitor';
    }

    public function isWalletLinked(): bool
    {
        return (bool) $this->wallet_linked;
    }

    public function isPaymentIssue(): bool
    {
        return $this->payment_transaction_id !== null
            || $this->payment_id !== null
            || $this->requiresPaymentIssueType();
    }

    public function requiresPaymentIssueType(): bool
    {
        if (! $this->issue_type) {
            return false;
        }

        $types = config('support.issue_types', []);

        return (bool) ($types[$this->issue_type]['requires_payment'] ?? false);
    }

    public function issueTypeLabel(): ?string
    {
        if (! $this->issue_type) {
            return null;
        }

        $types = config('support.issue_types', []);

        return isset($types[$this->issue_type]['label'])
            ? (string) $types[$this->issue_type]['label']
            : $this->issue_type;
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function whatsappWallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }

    public function replies()
    {
        return $this->hasMany(SupportTicketReply::class, 'ticket_id');
    }

    public function publicReplies()
    {
        return $this->replies()->where('is_internal_note', false);
    }
}

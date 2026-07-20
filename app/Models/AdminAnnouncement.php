<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAnnouncement extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const AUDIENCE_WALLET = 'wallet';

    public const AUDIENCE_RENTALS = 'rentals';

    public const AUDIENCE_BUSINESS = 'business';

    /** @var list<string> */
    public const AUDIENCES = [
        self::AUDIENCE_WALLET,
        self::AUDIENCE_RENTALS,
        self::AUDIENCE_BUSINESS,
    ];

    protected $fillable = [
        'admin_id',
        'title',
        'body',
        'audiences',
        'channel_email',
        'channel_push',
        'push_screen',
        'status',
        'recipients_estimated',
        'emails_sent',
        'emails_failed',
        'emails_skipped',
        'pushes_sent',
        'pushes_failed',
        'pushes_skipped',
        'error_summary',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'audiences' => 'array',
        'channel_email' => 'boolean',
        'channel_push' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function wantsEmail(): bool
    {
        return (bool) $this->channel_email;
    }

    public function wantsPush(): bool
    {
        return (bool) $this->channel_push;
    }

    /**
     * @return list<string>
     */
    public function audienceList(): array
    {
        $raw = is_array($this->audiences) ? $this->audiences : [];

        return array_values(array_intersect(self::AUDIENCES, array_map('strval', $raw)));
    }
}

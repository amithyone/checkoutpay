<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicketReply extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        static::created(function (SupportTicketReply $reply) {
            if ($reply->user_type !== 'admin' || (bool) $reply->is_internal_note) {
                return;
            }

            $ticket = $reply->ticket()->first();
            if (! $ticket || ! $ticket->business_id) {
                return;
            }

            try {
                app(\App\Services\PushNotificationService::class)->notifyBusiness(
                    (int) $ticket->business_id,
                    'New support message',
                    'Support has replied to your ticket.',
                    [
                        'type' => 'support_message',
                        'ticket_id' => (string) $ticket->id,
                        'reply_id' => (string) $reply->id,
                    ]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Push notify failed for support reply', [
                    'ticket_id' => $ticket->id,
                    'reply_id' => $reply->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    protected $fillable = [
        'ticket_id',
        'user_id',
        'user_type',
        'message',
        'attachments',
        'is_internal_note',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_internal_note' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the ticket this reply belongs to
     */
    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    /**
     * Get the user who made this reply
     */
    public function user()
    {
        if ($this->user_type === 'business') {
            return $this->belongsTo(Business::class, 'user_id');
        }
        return $this->belongsTo(Admin::class, 'user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalsAdminAppSessionEvent extends Model
{
    public const TYPE_LOGIN = 'login';

    public const TYPE_LOGOUT = 'logout';

    public const TYPE_SESSION_EXPIRED = 'session_expired';

    public const TYPE_HEARTBEAT = 'heartbeat';

    protected $fillable = [
        'rentals_admin_app_session_id',
        'admin_id',
        'admin_email',
        'event_type',
        'summary',
        'meta',
        'ip_address',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(RentalsAdminAppSession::class, 'rentals_admin_app_session_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}

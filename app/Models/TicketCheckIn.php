<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketCheckIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'checked_in_by',
        'check_in_method',
        'location',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    const METHOD_QR_SCAN = 'qr_scan';
    const METHOD_MANUAL = 'manual';

    /**
     * Get the ticket that was checked in
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the admin who performed the check-in
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'checked_in_by');
    }

    /**
     * Scope for QR scan check-ins
     */
    public function scopeQrScan($query)
    {
        return $query->where('check_in_method', self::METHOD_QR_SCAN);
    }

    /**
     * Scope for manual check-ins
     */
    public function scopeManual($query)
    {
        return $query->where('check_in_method', self::METHOD_MANUAL);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessDisbursementBatch extends Model
{
    protected $fillable = [
        'business_id',
        'kind',
        'status',
        'total_amount_ngn',
        'item_count',
        'success_count',
        'failed_count',
        'created_by_type',
        'created_by_id',
        'salary_schedule_id',
        'notes',
        'processed_at',
    ];

    protected $casts = [
        'total_amount_ngn' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(BusinessSalarySchedule::class, 'salary_schedule_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BusinessDisbursementItem::class, 'batch_id');
    }
}

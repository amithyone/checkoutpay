<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessSalarySchedule extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'cadence',
        'total_monthly_amount_ngn',
        'installment_count',
        'start_date',
        'end_date',
        'status',
        'employee_ids',
    ];

    protected $casts = [
        'total_monthly_amount_ngn' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'employee_ids' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(BusinessDisbursementBatch::class, 'salary_schedule_id');
    }
}

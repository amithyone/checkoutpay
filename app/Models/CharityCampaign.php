<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CharityCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'title',
        'slug',
        'story',
        'goal_amount',
        'raised_amount',
        'image',
        'currency',
        'end_date',
        'status',
        'is_featured',
    ];

    protected $casts = [
        'goal_amount' => 'decimal:2',
        'raised_amount' => 'decimal:2',
        'end_date' => 'date',
        'is_featured' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->title);
                $original = $model->slug;
                $count = 0;
                while (static::where('slug', $model->slug)->exists()) {
                    $count++;
                    $model->slug = $original . '-' . $count;
                }
            }
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function getProgressPercentAttribute(): float
    {
        if ((float) $this->goal_amount <= 0) {
            return 0;
        }
        return min(100, round((float) $this->raised_amount / (float) $this->goal_amount * 100, 1));
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RentalCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * Get rental items in this category
     */
    public function items()
    {
        return $this->hasMany(RentalItem::class, 'category_id');
    }

    /**
     * Get active items in this category
     */
    public function activeItems()
    {
        return $this->hasMany(RentalItem::class, 'category_id')
            ->where('is_active', true)
            ->where('is_available', true);
    }
}

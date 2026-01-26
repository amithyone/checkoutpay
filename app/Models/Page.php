<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'content',
        'meta_title',
        'meta_description',
        'is_published',
        'order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'content' => 'array', // Cast content to array for JSON storage
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get page by slug
     * OPTIMIZED: Uses caching to avoid database queries
     */
    public static function getBySlug(string $slug): ?self
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "page_{$slug}",
            3600, // Cache for 1 hour
            function () use ($slug) {
                return self::where('slug', $slug)
                    ->where('is_published', true)
                    ->first();
            }
        );
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Invalidate cache when page is updated
        static::saved(function ($page) {
            \Illuminate\Support\Facades\Cache::forget("page_{$page->slug}");
        });

        static::deleted(function ($page) {
            \Illuminate\Support\Facades\Cache::forget("page_{$page->slug}");
        });
    }
}

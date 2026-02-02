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
        'featured_image',
        'images',
        'meta_title',
        'meta_description',
        'is_published',
        'order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'images' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get content attribute - handle both JSON and HTML content
     */
    public function getContentAttribute($value)
    {
        // If value is JSON string, decode it
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            // Return as string if not valid JSON (HTML content)
            return $value;
        }
        return $value;
    }

    /**
     * Set content attribute - handle both JSON and HTML content
     */
    public function setContentAttribute($value)
    {
        // If it's an array, encode to JSON
        if (is_array($value)) {
            $this->attributes['content'] = json_encode($value);
        } else {
            // Store as string (for HTML content)
            $this->attributes['content'] = $value;
        }
    }

    /**
     * Get page by slug
     * OPTIMIZED: Uses caching to avoid database queries
     */
    public static function getBySlug(string $slug): ?self
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "page_{$slug}",
            86400, // Cache for 24 hours (pages rarely change)
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

    /**
     * Get featured image URL
     */
    public function getFeaturedImageUrlAttribute(): ?string
    {
        if (!$this->featured_image) {
            return null;
        }
        return \Illuminate\Support\Facades\Storage::url($this->featured_image);
    }

    /**
     * Get images URLs array
     */
    public function getImagesUrlsAttribute(): array
    {
        if (!$this->images || !is_array($this->images)) {
            return [];
        }
        
        return array_map(function ($image) {
            return \Illuminate\Support\Facades\Storage::url($image);
        }, $this->images);
    }
}

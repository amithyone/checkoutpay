<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'group',
    ];

    /**
     * Get a setting value by key
     * OPTIMIZED: Uses caching to avoid database queries on every call
     */
    public static function get(string $key, $default = null)
    {
        // Cache settings for 1 hour to avoid database queries on every page load
        return \Illuminate\Support\Facades\Cache::remember(
            "setting_{$key}",
            3600, // 1 hour cache
            function () use ($key, $default) {
                $setting = self::where('key', $key)->first();
                
                if (!$setting) {
                    return $default;
                }

                return self::castValue($setting->value, $setting->type);
            }
        );
    }

    /**
     * Set a setting value by key
     * OPTIMIZED: Invalidates cache when setting is updated
     */
    public static function set(string $key, $value, string $type = 'string', string $group = 'general', ?string $description = null): void
    {
        self::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) || is_object($value) ? json_encode($value) : $value,
                'type' => $type,
                'group' => $group,
                'description' => $description,
            ]
        );
        
        // Invalidate cache when setting is updated
        \Illuminate\Support\Facades\Cache::forget("setting_{$key}");
    }

    /**
     * Cast value based on type
     */
    protected static function castValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer', 'int' => (int) $value,
            'float', 'double' => (float) $value,
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Get all settings by group
     */
    public static function getByGroup(string $group): array
    {
        return self::where('group', $group)
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => self::castValue($setting->value, $setting->type)];
            })
            ->toArray();
    }
}

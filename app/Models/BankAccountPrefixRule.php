<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class BankAccountPrefixRule extends Model
{
    protected $fillable = [
        'prefix',
        'bank_code',
        'bank_name',
        'is_active',
        'notes',
        'created_by_admin_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        $flush = static function (): void {
            Cache::forget(self::cacheKey());
        };

        static::saved($flush);
        static::deleted($flush);
    }

    public static function cacheKey(): string
    {
        return 'bank_account_prefix_rules:v1';
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<self>  $query */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    /**
     * @return list<array{prefix: string, code: string, name: string}>
     */
    public static function rulesForSuggestions(): array
    {
        return Cache::remember(self::cacheKey(), now()->addMinutes(10), function () {
            if (! Schema::hasTable('bank_account_prefix_rules')) {
                return self::rulesFromConfig();
            }

            $rows = self::query()
                ->active()
                ->orderByDesc('prefix')
                ->get(['prefix', 'bank_code', 'bank_name']);

            if ($rows->isEmpty()) {
                return self::rulesFromConfig();
            }

            $rules = [];
            foreach ($rows as $row) {
                $rules[] = [
                    'prefix' => (string) $row->prefix,
                    'code' => (string) $row->bank_code,
                    'name' => trim((string) ($row->bank_name ?? '')),
                ];
            }

            return $rules;
        });
    }

    /**
     * @return list<array{prefix: string, code: string, name: string}>
     */
    private static function rulesFromConfig(): array
    {
        $config = config('bank_account_prefixes.rules', []);
        if (! is_array($config)) {
            return [];
        }

        $rules = [];
        foreach ($config as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $prefix = preg_replace('/\D+/', '', (string) ($rule['prefix'] ?? '')) ?? '';
            $code = trim((string) ($rule['code'] ?? ''));
            if ($prefix === '' || strlen($prefix) < 2 || $code === '') {
                continue;
            }
            $rules[] = [
                'prefix' => $prefix,
                'code' => $code,
                'name' => trim((string) ($rule['name'] ?? '')),
            ];
        }

        return $rules;
    }
}

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
        return 'bank_account_prefix_rules:v3';
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
     * Flat prefix→bank rows for matching (config ∪ active DB; DB wins on same prefix+code).
     *
     * @return list<array{prefix: string, code: string, name: string, category?: string}>
     */
    public static function rulesForSuggestions(): array
    {
        return Cache::remember(self::cacheKey(), now()->addMinutes(10), function () {
            /** @var array<string, array{prefix: string, code: string, name: string, category?: string}> $byKey */
            $byKey = [];

            foreach (self::rulesFromConfig() as $rule) {
                $byKey[$rule['prefix'].'|'.$rule['code']] = $rule;
            }

            if (Schema::hasTable('bank_account_prefix_rules')) {
                $rows = self::query()
                    ->active()
                    ->orderByDesc('prefix')
                    ->get(['prefix', 'bank_code', 'bank_name']);

                foreach ($rows as $row) {
                    $prefix = preg_replace('/\D+/', '', (string) $row->prefix) ?? '';
                    $code = trim((string) $row->bank_code);
                    if ($prefix === '' || strlen($prefix) < 2 || $code === '') {
                        continue;
                    }
                    $key = $prefix.'|'.$code;
                    $byKey[$key] = [
                        'prefix' => $prefix,
                        'code' => $code,
                        'name' => trim((string) ($row->bank_name ?? '')),
                    ];
                }
            }

            $rules = array_values($byKey);
            usort($rules, function (array $a, array $b) {
                $len = strlen($b['prefix']) <=> strlen($a['prefix']);
                if ($len !== 0) {
                    return $len;
                }

                return strcmp($a['code'], $b['code']);
            });

            return $rules;
        });
    }

    /**
     * @return list<array{prefix: string, code: string, name: string, category?: string}>
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
            $row = [
                'prefix' => $prefix,
                'code' => $code,
                'name' => trim((string) ($rule['name'] ?? '')),
            ];
            $category = trim((string) ($rule['category'] ?? ''));
            if ($category !== '') {
                $row['category'] = $category;
            }
            $rules[] = $row;
        }

        return $rules;
    }
}

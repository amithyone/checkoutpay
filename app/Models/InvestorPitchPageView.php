<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvestorPitchPageView extends Model
{
    public const PAGE_GATE = 'gate';

    public const PAGE_UNLOCK = 'unlock';

    public const PAGE_PITCH = 'pitch';

    public const PAGE_SUMMARY = 'summary';

    protected $fillable = [
        'investor_pitch_access_id',
        'page_key',
        'path',
        'ip',
        'user_agent',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function access(): BelongsTo
    {
        return $this->belongsTo(InvestorPitchAccess::class, 'investor_pitch_access_id');
    }

    public static function record(InvestorPitchAccess $access, string $pageKey, Request $request): self
    {
        $ua = $request->userAgent();

        return self::query()->create([
            'investor_pitch_access_id' => $access->id,
            'page_key' => $pageKey,
            'path' => '/'.ltrim($request->path(), '/'),
            'ip' => $request->ip(),
            'user_agent' => $ua ? Str::limit($ua, 500, '') : null,
            'viewed_at' => now(),
        ]);
    }

    public function label(): string
    {
        return match ($this->page_key) {
            self::PAGE_GATE => 'Opened lock / gate page',
            self::PAGE_UNLOCK => 'Unlocked with password + NDA',
            self::PAGE_PITCH => 'Opened full investor pitch',
            self::PAGE_SUMMARY => 'Opened executive summary',
            default => $this->page_key,
        };
    }
}

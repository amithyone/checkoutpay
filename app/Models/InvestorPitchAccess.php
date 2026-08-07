<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InvestorPitchAccess extends Model
{
    protected $fillable = [
        'name',
        'email',
        'company',
        'access_token',
        'password',
        'is_active',
        'nda_accepted_at',
        'last_accessed_at',
        'access_count',
        'notes',
        'created_by_admin_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'nda_accepted_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'access_count' => 'integer',
    ];

    public static function generateToken(): string
    {
        return Str::lower(Str::random(32));
    }

    public function checkPassword(string $plain): bool
    {
        return Hash::check($plain, $this->password);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(InvestorPitchPageView::class)->orderByDesc('viewed_at');
    }

    public function gateUrl(): string
    {
        return route('investor.gate', ['token' => $this->access_token]);
    }

    public function recordSuccessfulAccess(): void
    {
        $this->forceFill([
            'last_accessed_at' => now(),
            'access_count' => $this->access_count + 1,
            'nda_accepted_at' => $this->nda_accepted_at ?? now(),
        ])->save();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Bank extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'logo_path',
        'logo_source',
    ];

    public function hasLogo(): bool
    {
        return is_string($this->logo_path) && $this->logo_path !== '';
    }

    public function logoUrl(): ?string
    {
        if (! $this->hasLogo()) {
            return null;
        }

        $disk = (string) config('bank_logos.disk', 'public');

        return Storage::disk($disk)->url($this->logo_path);
    }

    /**
     * @return array{code: string, name: string, logo_url: string|null}
     */
    public function toApiArray(): array
    {
        return [
            'code' => (string) $this->code,
            'name' => (string) $this->name,
            'logo_url' => $this->logoUrl(),
        ];
    }
}

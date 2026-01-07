<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhitelistedEmailAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Check if an email address matches this whitelist entry
     */
    public function matches(string $email): bool
    {
        $email = strtolower(trim($email));
        $whitelistEmail = strtolower(trim($this->email));

        // Exact match
        if ($email === $whitelistEmail) {
            return true;
        }

        // Domain match (if whitelist entry starts with @)
        if (str_starts_with($whitelistEmail, '@')) {
            $domain = substr($whitelistEmail, 1);
            return str_ends_with($email, '@' . $domain);
        }

        // Email contains whitelist entry (for partial matches)
        if (str_contains($email, $whitelistEmail)) {
            return true;
        }

        return false;
    }

    /**
     * Check if an email address is whitelisted
     */
    public static function isWhitelisted(string $email): bool
    {
        $whitelisted = self::where('is_active', true)->get();
        
        foreach ($whitelisted as $entry) {
            if ($entry->matches($email)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scope to get active whitelisted emails
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

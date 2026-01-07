<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankEmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_name',
        'sender_email',
        'sender_domain',
        'sample_html',
        'sample_text',
        'amount_pattern',
        'sender_name_pattern',
        'account_number_pattern',
        'amount_field_label',
        'sender_name_field_label',
        'account_number_field_label',
        'extraction_notes',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Check if an email matches this template
     */
    public function matchesEmail(string $fromEmail): bool
    {
        $fromEmail = strtolower(trim($fromEmail));
        
        // Extract email from "Name <email@domain.com>" format
        if (preg_match('/<(.+?)>/', $fromEmail, $matches)) {
            $fromEmail = strtolower(trim($matches[1]));
        }

        // Check sender email match
        if ($this->sender_email) {
            if (strtolower($this->sender_email) === $fromEmail) {
                return true;
            }
        }

        // Check sender domain match
        if ($this->sender_domain) {
            $domain = ltrim(strtolower($this->sender_domain), '@');
            if (str_ends_with($fromEmail, '@' . $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scope to get active templates ordered by priority
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('priority', 'desc');
    }
}

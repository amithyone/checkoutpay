<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GtbankTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_number',
        'amount',
        'sender_name',
        'transaction_type',
        'value_date',
        'narration',
        'bank_name',
        'duplicate_hash',
        'processed_email_id',
        'bank_template_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'value_date' => 'date',
    ];

    /**
     * Get the processed email that generated this transaction
     */
    public function processedEmail(): BelongsTo
    {
        return $this->belongsTo(ProcessedEmail::class);
    }

    /**
     * Get the bank template used to extract this transaction
     */
    public function bankTemplate(): BelongsTo
    {
        return $this->belongsTo(BankEmailTemplate::class, 'bank_template_id');
    }

    /**
     * Generate duplicate hash for a transaction
     */
    public static function generateDuplicateHash(string $accountNumber, float $amount, string $valueDate, ?string $narration): string
    {
        $data = [
            'account_number' => trim($accountNumber),
            'amount' => number_format($amount, 2, '.', ''),
            'value_date' => $valueDate,
            'narration' => trim($narration ?? ''),
        ];
        
        return hash('sha256', json_encode($data));
    }

    /**
     * Check if a transaction with this hash already exists
     */
    public static function isDuplicate(string $duplicateHash): bool
    {
        return static::where('duplicate_hash', $duplicateHash)->exists();
    }
}

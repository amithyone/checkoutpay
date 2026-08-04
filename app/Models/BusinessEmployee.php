<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessEmployee extends Model
{
    public const METHOD_BANK = 'bank';

    public const METHOD_WALLET = 'wallet';

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCY_BIWEEKLY = 'biweekly';

    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCY_DAILY = 'daily';

    /** Average days used for daily / weekly estimates from a monthly figure. */
    public const DAYS_PER_MONTH = 30;

    protected $fillable = [
        'business_id',
        'name',
        'payment_method',
        'phone_e164',
        'bank_code',
        'account_number',
        'account_name',
        'monthly_salary_ngn',
        'pay_frequency',
        'pay_day_hint',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'monthly_salary_ngn' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public static function frequencyOptions(): array
    {
        return [
            self::FREQUENCY_MONTHLY => 'Monthly (once a month)',
            self::FREQUENCY_BIWEEKLY => 'Every 2 weeks',
            self::FREQUENCY_WEEKLY => 'Weekly',
            self::FREQUENCY_DAILY => 'Daily (trickle)',
        ];
    }

    public function monthlyAmount(): float
    {
        return max(0, (float) $this->monthly_salary_ngn);
    }

    /** Estimated daily take-home from monthly salary (monthly ÷ 30). */
    public function dailyAmount(): float
    {
        return round($this->monthlyAmount() / self::DAYS_PER_MONTH, 2);
    }

    public function weeklyAmount(): float
    {
        return round($this->dailyAmount() * 7, 2);
    }

    public function biweeklyAmount(): float
    {
        return round($this->dailyAmount() * 14, 2);
    }

    /**
     * Amount paid each cycle for this employee's preferred frequency.
     */
    public function amountPerPayCycle(): float
    {
        return match ($this->pay_frequency ?: self::FREQUENCY_MONTHLY) {
            self::FREQUENCY_DAILY => $this->dailyAmount(),
            self::FREQUENCY_WEEKLY => $this->weeklyAmount(),
            self::FREQUENCY_BIWEEKLY => $this->biweeklyAmount(),
            default => $this->monthlyAmount(),
        };
    }

    public function installmentsPerMonth(): int
    {
        return match ($this->pay_frequency ?: self::FREQUENCY_MONTHLY) {
            self::FREQUENCY_DAILY => self::DAYS_PER_MONTH,
            self::FREQUENCY_WEEKLY => 4,
            self::FREQUENCY_BIWEEKLY => 2,
            default => 1,
        };
    }

    public function frequencyLabel(): string
    {
        return self::frequencyOptions()[$this->pay_frequency] ?? 'Monthly';
    }

    public function paymentDestinationLabel(): string
    {
        if ($this->payment_method === self::METHOD_WALLET) {
            return 'Wallet '.($this->phone_e164 ?: '—');
        }

        $acct = $this->account_number ? ' · '.$this->account_number : '';

        return trim(($this->account_name ?: 'Bank').$acct);
    }
}

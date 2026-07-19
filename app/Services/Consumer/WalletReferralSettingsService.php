<?php

namespace App\Services\Consumer;

use App\Models\Setting;

/**
 * Runtime referral commercial settings. Admin Setting keys override config defaults.
 */
final class WalletReferralSettingsService
{
    public function enabled(): bool
    {
        return (bool) $this->value('referral_enabled', (bool) config('consumer_wallet.referral.enabled', true), 'boolean');
    }

    public function bonusMonths(): int
    {
        return max(1, (int) $this->value(
            'referral_bonus_months',
            (int) config('consumer_wallet.referral.bonus_months', 6),
            'integer'
        ));
    }

    public function firstDepositPercent(): float
    {
        $pct = (float) $this->value(
            'referral_first_deposit_percent',
            (float) config('consumer_wallet.referral.first_deposit_percent', 5),
            'float'
        );

        return max(0.0, min(100.0, $pct));
    }

    public function firstDepositMaxNgn(): ?float
    {
        $raw = $this->value(
            'referral_first_deposit_max_ngn',
            config('consumer_wallet.referral.first_deposit_max_ngn'),
            'float'
        );
        if ($raw === null || $raw === '') {
            return null;
        }
        $max = (float) $raw;

        return $max > 0 ? $max : null;
    }

    public function firstDepositMinNgn(): float
    {
        return max(0.0, (float) $this->value(
            'referral_first_deposit_min_ngn',
            (float) config('consumer_wallet.referral.first_deposit_min_ngn', 0),
            'float'
        ));
    }

    public function milestoneEvery(): int
    {
        return max(1, (int) $this->value(
            'referral_milestone_every',
            (int) config('consumer_wallet.referral.milestone_every', 100),
            'integer'
        ));
    }

    public function milestoneAmountNgn(): float
    {
        return max(0.0, (float) $this->value(
            'referral_milestone_amount_ngn',
            (float) config('consumer_wallet.referral.milestone_amount_ngn', 200),
            'float'
        ));
    }

    public function leaderboardEnabled(): bool
    {
        return (bool) $this->value(
            'referral_leaderboard_enabled',
            (bool) config('consumer_wallet.referral.leaderboard_enabled', true),
            'boolean'
        );
    }

    public function leaderboardMonthPotNgn(): float
    {
        return max(0.0, (float) $this->value(
            'referral_leaderboard_month_pot_ngn',
            (float) config('consumer_wallet.referral.leaderboard_month_pot_ngn', 0),
            'float'
        ));
    }

    public function leaderboardTopN(): int
    {
        return max(1, (int) $this->value(
            'referral_leaderboard_top_n',
            (int) config('consumer_wallet.referral.leaderboard_top_n', 10),
            'integer'
        ));
    }

    /**
     * @return string|'equal'|list<float>
     */
    public function leaderboardSplit(): string|array
    {
        $raw = $this->value(
            'referral_leaderboard_split',
            config('consumer_wallet.referral.leaderboard_split', 'equal'),
            'string'
        );
        if (is_array($raw)) {
            return $raw;
        }
        $str = trim((string) $raw);
        if ($str === '' || strtolower($str) === 'equal') {
            return 'equal';
        }
        $decoded = json_decode($str, true);
        if (is_array($decoded) && $decoded !== []) {
            return array_map('floatval', $decoded);
        }

        return 'equal';
    }

    public function timezone(): string
    {
        $tz = trim((string) config('consumer_wallet.referral.timezone', 'Africa/Lagos'));

        return $tz !== '' ? $tz : 'Africa/Lagos';
    }

    /**
     * Snapshot of commercial knobs for bonus meta audit.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'enabled' => $this->enabled(),
            'bonus_months' => $this->bonusMonths(),
            'first_deposit_percent' => $this->firstDepositPercent(),
            'first_deposit_max_ngn' => $this->firstDepositMaxNgn(),
            'first_deposit_min_ngn' => $this->firstDepositMinNgn(),
            'milestone_every' => $this->milestoneEvery(),
            'milestone_amount_ngn' => $this->milestoneAmountNgn(),
            'leaderboard_enabled' => $this->leaderboardEnabled(),
            'leaderboard_month_pot_ngn' => $this->leaderboardMonthPotNgn(),
            'leaderboard_top_n' => $this->leaderboardTopN(),
            'leaderboard_split' => $this->leaderboardSplit(),
        ];
    }

    /**
     * Public rules payload for native app UI.
     *
     * @return array<string, mixed>
     */
    public function publicRules(): array
    {
        $base = rtrim((string) config('app.url', 'https://check-outpay.com'), '/');

        return [
            'enabled' => $this->enabled(),
            'bonus_months' => $this->bonusMonths(),
            'first_deposit_percent' => $this->firstDepositPercent(),
            'first_deposit_max_ngn' => $this->firstDepositMaxNgn(),
            'first_deposit_min_ngn' => $this->firstDepositMinNgn(),
            'milestone_every' => $this->milestoneEvery(),
            'milestone_amount_ngn' => $this->milestoneAmountNgn(),
            'milestone_currency' => 'NGN',
            'leaderboard_enabled' => $this->leaderboardEnabled(),
            'leaderboard_top_n' => $this->leaderboardTopN(),
            'terms_url' => $base.'/terms-and-conditions#referral-programme',
            'faq_url' => $base.'/faqs?category=whatsapp-wallet',
        ];
    }

    /**
     * Persist admin form values.
     *
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, message: string, errors?: array<string, string>}
     */
    public function saveFromAdmin(array $input): array
    {
        $errors = [];

        $enabled = filter_var($input['referral_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $months = (int) ($input['referral_bonus_months'] ?? 0);
        if ($months < 1 || $months > 60) {
            $errors['referral_bonus_months'] = 'Bonus months must be between 1 and 60.';
        }
        $pct = (float) ($input['referral_first_deposit_percent'] ?? -1);
        if ($pct < 0 || $pct > 100) {
            $errors['referral_first_deposit_percent'] = 'First deposit percent must be 0–100.';
        }
        $every = (int) ($input['referral_milestone_every'] ?? 0);
        if ($every < 1) {
            $errors['referral_milestone_every'] = 'Milestone every must be at least 1.';
        }
        $milestoneAmt = (float) ($input['referral_milestone_amount_ngn'] ?? -1);
        if ($milestoneAmt < 0) {
            $errors['referral_milestone_amount_ngn'] = 'Milestone amount must be ≥ 0.';
        }
        $pot = (float) ($input['referral_leaderboard_month_pot_ngn'] ?? -1);
        if ($pot < 0) {
            $errors['referral_leaderboard_month_pot_ngn'] = 'Leaderboard pot must be ≥ 0.';
        }
        $topN = (int) ($input['referral_leaderboard_top_n'] ?? 0);
        if ($topN < 1 || $topN > 100) {
            $errors['referral_leaderboard_top_n'] = 'Leaderboard top N must be 1–100.';
        }

        $maxRaw = trim((string) ($input['referral_first_deposit_max_ngn'] ?? ''));
        $max = $maxRaw === '' ? null : (float) $maxRaw;
        if ($max !== null && $max < 0) {
            $errors['referral_first_deposit_max_ngn'] = 'Max first-deposit bonus must be ≥ 0 or empty.';
        }

        $min = max(0.0, (float) ($input['referral_first_deposit_min_ngn'] ?? 0));
        $split = trim((string) ($input['referral_leaderboard_split'] ?? 'equal'));
        if ($split !== '' && strtolower($split) !== 'equal') {
            $decoded = json_decode($split, true);
            if (! is_array($decoded)) {
                $errors['referral_leaderboard_split'] = 'Split must be “equal” or a JSON array of percentages.';
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'message' => 'Fix the highlighted fields.', 'errors' => $errors];
        }

        Setting::set('referral_enabled', $enabled ? '1' : '0', 'boolean', 'referral', 'Wallet referral programme master switch');
        Setting::set('referral_bonus_months', (string) $months, 'integer', 'referral', 'Referral bonus window months');
        Setting::set('referral_first_deposit_percent', (string) $pct, 'float', 'referral', 'First deposit bonus percent');
        Setting::set(
            'referral_first_deposit_max_ngn',
            $max === null ? '' : (string) $max,
            'float',
            'referral',
            'Optional max first-deposit bonus NGN'
        );
        Setting::set('referral_first_deposit_min_ngn', (string) $min, 'float', 'referral', 'Min top-up for first-deposit bonus');
        Setting::set('referral_milestone_every', (string) $every, 'integer', 'referral', 'Counted txs per milestone');
        Setting::set('referral_milestone_amount_ngn', (string) $milestoneAmt, 'float', 'referral', 'Milestone bonus NGN');
        Setting::set(
            'referral_leaderboard_enabled',
            filter_var($input['referral_leaderboard_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'boolean',
            'referral',
            'Monthly referral leaderboard'
        );
        Setting::set('referral_leaderboard_month_pot_ngn', (string) $pot, 'float', 'referral', 'Monthly leaderboard pot NGN');
        Setting::set('referral_leaderboard_top_n', (string) $topN, 'integer', 'referral', 'Leaderboard top N');
        Setting::set(
            'referral_leaderboard_split',
            $split === '' ? 'equal' : $split,
            'string',
            'referral',
            'Leaderboard prize split (equal or JSON %)'
        );

        return ['ok' => true, 'message' => 'Referral settings saved.'];
    }

    private function value(string $key, mixed $default, string $type): mixed
    {
        $stored = Setting::get($key, null);
        if ($stored === null || $stored === '') {
            return $default;
        }

        return match ($type) {
            'boolean' => filter_var($stored, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $stored,
            'float' => (float) $stored,
            default => $stored,
        };
    }
}

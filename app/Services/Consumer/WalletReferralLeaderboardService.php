<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletReferral;
use App\Models\WhatsappWalletReferralBonus;
use App\Models\WhatsappWalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class WalletReferralLeaderboardService
{
    public function __construct(
        private WalletReferralSettingsService $settings,
        private WalletReferralBonusService $bonuses,
    ) {}

    /**
     * @return array{ok: bool, message: string, paid?: int, month?: string}
     */
    public function settleMonth(?string $yearMonth = null): array
    {
        if (! $this->settings->enabled() || ! $this->settings->leaderboardEnabled()) {
            return ['ok' => true, 'message' => 'Leaderboard disabled.', 'paid' => 0];
        }

        $pot = $this->settings->leaderboardMonthPotNgn();
        if ($pot <= 0) {
            return ['ok' => true, 'message' => 'Leaderboard pot is zero.', 'paid' => 0];
        }

        $tz = $this->settings->timezone();
        $month = $yearMonth
            ? Carbon::createFromFormat('Y-m', $yearMonth, $tz)->startOfMonth()
            : Carbon::now($tz)->subMonthNoOverflow()->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $periodKey = $start->format('Y-m');

        $idempotencyPrefix = 'leaderboard:'.$periodKey.':';
        if (WhatsappWalletReferralBonus::query()
            ->where('type', WhatsappWalletReferralBonus::TYPE_LEADERBOARD)
            ->where('idempotency_key', 'like', $idempotencyPrefix.'%')
            ->exists()) {
            return ['ok' => true, 'message' => 'Already settled for '.$periodKey, 'paid' => 0, 'month' => $periodKey];
        }

        $rows = $this->rankingsForPeriod($start, $end, $this->settings->leaderboardTopN());
        if ($rows === []) {
            return ['ok' => true, 'message' => 'No ranking activity.', 'paid' => 0, 'month' => $periodKey];
        }

        $shares = $this->prizeAmounts($pot, count($rows));
        $paid = 0;

        foreach ($rows as $index => $row) {
            $amount = $shares[$index] ?? 0.0;
            if ($amount <= 0) {
                continue;
            }

            $referral = WhatsappWalletReferral::query()
                ->where('referrer_wallet_id', $row['referrer_wallet_id'])
                ->orderByDesc('id')
                ->first();
            if (! $referral) {
                // Synthetic: use first referred wallet as anchor for FK, or skip
                $referral = WhatsappWalletReferral::query()
                    ->where('referrer_wallet_id', $row['referrer_wallet_id'])
                    ->first();
            }
            if (! $referral) {
                continue;
            }

            $ok = $this->bonuses->payBonus(
                $referral,
                WhatsappWalletReferralBonus::TYPE_LEADERBOARD,
                $amount,
                'NGN',
                $idempotencyPrefix.'rank'.($index + 1).':ref'.$row['referrer_wallet_id'],
                WhatsappWalletTransaction::TYPE_REFERRAL_BONUS_LEADERBOARD,
                [
                    'period' => $periodKey,
                    'rank' => $index + 1,
                    'score' => $row['score'],
                    'snapshot' => $this->settings->snapshot(),
                ],
            );
            if ($ok) {
                $paid++;
            }
        }

        Log::info('wallet.referral.leaderboard_settled', [
            'month' => $periodKey,
            'paid' => $paid,
            'pot' => $pot,
        ]);

        return ['ok' => true, 'message' => 'Settled '.$periodKey, 'paid' => $paid, 'month' => $periodKey];
    }

    /**
     * @return list<array{referrer_wallet_id: int, score: int, display_name: string, masked_phone: string}>
     */
    public function currentMonthStandings(?int $limit = null): array
    {
        $tz = $this->settings->timezone();
        $start = Carbon::now($tz)->startOfMonth();
        $end = Carbon::now($tz)->endOfMonth();

        return $this->rankingsForPeriod($start, $end, $limit ?? $this->settings->leaderboardTopN());
    }

    /**
     * @return array{rank: int|null, score: int}
     */
    public function positionForReferrer(int $referrerWalletId): array
    {
        $all = $this->currentMonthStandings(500);
        foreach ($all as $i => $row) {
            if ((int) $row['referrer_wallet_id'] === $referrerWalletId) {
                return ['rank' => $i + 1, 'score' => (int) $row['score']];
            }
        }

        $tz = $this->settings->timezone();
        $score = $this->scoreForReferrer(
            $referrerWalletId,
            Carbon::now($tz)->startOfMonth(),
            Carbon::now($tz)->endOfMonth(),
        );

        return ['rank' => null, 'score' => $score];
    }

    /**
     * @return list<array{referrer_wallet_id: int, score: int, display_name: string, masked_phone: string}>
     */
    private function rankingsForPeriod(Carbon $start, Carbon $end, int $limit): array
    {
        $counted = WhatsappWalletTransaction::REFERRAL_COUNTED_TYPES;

        $scores = WhatsappWalletTransaction::query()
            ->select('whatsapp_wallet_referrals.referrer_wallet_id', DB::raw('COUNT(*) as score'))
            ->join('whatsapp_wallet_referrals', 'whatsapp_wallet_referrals.referred_wallet_id', '=', 'whatsapp_wallet_transactions.whatsapp_wallet_id')
            ->whereIn('whatsapp_wallet_transactions.type', $counted)
            ->where('whatsapp_wallet_transactions.created_at', '>=', $start->copy()->timezone(config('app.timezone')))
            ->where('whatsapp_wallet_transactions.created_at', '<=', $end->copy()->timezone(config('app.timezone')))
            ->groupBy('whatsapp_wallet_referrals.referrer_wallet_id')
            ->orderByDesc('score')
            ->limit(max(1, $limit))
            ->get();

        $out = [];
        foreach ($scores as $row) {
            $wallet = WhatsappWallet::query()->find((int) $row->referrer_wallet_id);
            if (! $wallet) {
                continue;
            }
            $out[] = [
                'referrer_wallet_id' => (int) $wallet->id,
                'score' => (int) $row->score,
                'display_name' => $this->displayName($wallet),
                'masked_phone' => $this->maskPhone((string) $wallet->phone_e164),
            ];
        }

        return $out;
    }

    private function scoreForReferrer(int $referrerWalletId, Carbon $start, Carbon $end): int
    {
        return (int) WhatsappWalletTransaction::query()
            ->join('whatsapp_wallet_referrals', 'whatsapp_wallet_referrals.referred_wallet_id', '=', 'whatsapp_wallet_transactions.whatsapp_wallet_id')
            ->where('whatsapp_wallet_referrals.referrer_wallet_id', $referrerWalletId)
            ->whereIn('whatsapp_wallet_transactions.type', WhatsappWalletTransaction::REFERRAL_COUNTED_TYPES)
            ->where('whatsapp_wallet_transactions.created_at', '>=', $start->copy()->timezone(config('app.timezone')))
            ->where('whatsapp_wallet_transactions.created_at', '<=', $end->copy()->timezone(config('app.timezone')))
            ->count();
    }

    /**
     * @return list<float>
     */
    private function prizeAmounts(float $pot, int $winners): array
    {
        if ($winners < 1 || $pot <= 0) {
            return [];
        }

        $split = $this->settings->leaderboardSplit();
        if (is_array($split) && count($split) >= $winners) {
            $amounts = [];
            for ($i = 0; $i < $winners; $i++) {
                $amounts[] = round($pot * ((float) $split[$i] / 100), 2);
            }

            return $amounts;
        }

        if ($winners === 3 && (! is_array($split) || $split === 'equal')) {
            // Sensible default when exactly 3 and split is equal string — still equal unless custom
        }

        $each = round($pot / $winners, 2);
        $amounts = array_fill(0, $winners, $each);
        $diff = round($pot - array_sum($amounts), 2);
        if (abs($diff) >= 0.01) {
            $amounts[0] = round($amounts[0] + $diff, 2);
        }

        return $amounts;
    }

    private function displayName(WhatsappWallet $wallet): string
    {
        $name = trim((string) ($wallet->sender_name ?? ''));
        if ($name !== '') {
            return $name;
        }
        $f = trim((string) ($wallet->kyc_fname ?? ''));

        return $f !== '' ? $f : 'Checkout user';
    }

    private function maskPhone(string $e164): string
    {
        $d = preg_replace('/\D/', '', $e164) ?? '';
        if (strlen($d) < 8) {
            return '••••';
        }

        return substr($d, 0, 3).'••••'.substr($d, -3);
    }
}

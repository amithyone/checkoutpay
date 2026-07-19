<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletReferral;
use App\Models\WhatsappWalletReferralBonus;
use App\Services\Consumer\ConsumerWalletPayCodeService;
use App\Services\Consumer\WalletReferralLeaderboardService;
use App\Services\Consumer\WalletReferralSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerReferralController extends Controller
{
    public function __construct(
        private WalletReferralSettingsService $settings,
        private ConsumerWalletPayCodeService $payCodes,
        private WalletReferralLeaderboardService $leaderboard,
    ) {}

    public function rules(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->settings->publicRules(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $wallet = $this->wallet($request);
        $code = $this->payCodes->ensureForWallet($wallet);
        $asReferrer = WhatsappWalletReferral::query()->where('referrer_wallet_id', $wallet->id);
        $referredCount = (clone $asReferrer)->count();
        $activeCount = (clone $asReferrer)->where('bonus_ends_at', '>', now())->count();
        $countedTx = (int) (clone $asReferrer)->sum('counted_tx_total');
        $earned = (float) WhatsappWalletReferralBonus::query()
            ->where('referrer_wallet_id', $wallet->id)
            ->sum('amount');
        $pos = $this->leaderboard->positionForReferrer((int) $wallet->id);
        $myReferral = WhatsappWalletReferral::query()->where('referred_wallet_id', $wallet->id)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $this->settings->enabled(),
                'pay_code' => $code,
                'phone_e164' => (string) $wallet->phone_e164,
                'stats' => [
                    'referred_count' => $referredCount,
                    'active_referrals' => $activeCount,
                    'counted_tx_total' => $countedTx,
                    'bonuses_earned_total' => round($earned, 2),
                    'leaderboard_rank' => $pos['rank'],
                    'leaderboard_score' => $pos['score'],
                ],
                'referred_by' => $myReferral ? [
                    'attribution_source' => $myReferral->attribution_source,
                    'attributed_at' => $myReferral->attributed_at?->toIso8601String(),
                    'bonus_ends_at' => $myReferral->bonus_ends_at?->toIso8601String(),
                    'window_active' => $myReferral->isBonusWindowOpen(),
                ] : null,
                'rules' => $this->settings->publicRules(),
            ],
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        $wallet = $this->wallet($request);
        $code = $this->payCodes->ensureForWallet($wallet);
        $phone = (string) $wallet->phone_e164;
        $brand = (string) config('whatsapp.bot_brand_name', 'CheckoutNow');
        $base = rtrim((string) config('app.url', 'https://check-outpay.com'), '/');

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $this->settings->enabled(),
                'pay_code' => $code,
                'phone_e164' => $phone,
                'share_text' => "Join me on {$brand}! Use my code {$code} (or my number) when you sign up — or let me send you your first wallet transfer. {$base}",
                'deep_link_hint' => $base.'/?ref='.urlencode($code),
            ],
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $wallet = $this->wallet($request);
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));

        $paginator = WhatsappWalletReferral::query()
            ->with('referredWallet')
            ->where('referrer_wallet_id', $wallet->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = collect($paginator->items())->map(function (WhatsappWalletReferral $r) {
            $w = $r->referredWallet;

            return [
                'id' => $r->id,
                'masked_phone' => $this->maskPhone((string) ($w?->phone_e164 ?? '')),
                'attribution_source' => $r->attribution_source,
                'attributed_at' => $r->attributed_at?->toIso8601String(),
                'bonus_ends_at' => $r->bonus_ends_at?->toIso8601String(),
                'counted_tx_total' => (int) $r->counted_tx_total,
                'milestones_paid' => (int) $r->milestones_paid,
                'status' => $r->isBonusWindowOpen() ? 'active' : 'expired',
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    public function bonuses(Request $request): JsonResponse
    {
        $wallet = $this->wallet($request);
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));

        $paginator = WhatsappWalletReferralBonus::query()
            ->where('referrer_wallet_id', $wallet->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = collect($paginator->items())->map(fn (WhatsappWalletReferralBonus $b) => [
            'id' => $b->id,
            'type' => $b->type,
            'amount' => (float) $b->amount,
            'currency' => $b->currency,
            'created_at' => $b->created_at?->toIso8601String(),
            'meta' => $b->meta,
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $wallet = $this->wallet($request);
        $standings = $this->leaderboard->currentMonthStandings();
        $pos = $this->leaderboard->positionForReferrer((int) $wallet->id);

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $this->settings->enabled() && $this->settings->leaderboardEnabled(),
                'month' => now($this->settings->timezone())->format('Y-m'),
                'standings' => $standings,
                'me' => [
                    'rank' => $pos['rank'],
                    'score' => $pos['score'],
                ],
            ],
        ]);
    }

    private function wallet(Request $request): WhatsappWallet
    {
        /** @var ConsumerWalletApiAccount $account */
        $account = $request->user();
        $wallet = $account->wallet ?? WhatsappWallet::query()->find($account->whatsapp_wallet_id);
        if (! $wallet) {
            abort(response()->json(['success' => false, 'message' => 'Wallet not found.'], 404));
        }

        return $wallet;
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

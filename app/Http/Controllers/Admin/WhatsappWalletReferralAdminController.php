<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappWalletReferral;
use App\Models\WhatsappWalletReferralBonus;
use App\Services\Consumer\WalletReferralLeaderboardService;
use App\Services\Consumer\WalletReferralSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsappWalletReferralAdminController extends Controller
{
    public function index(
        Request $request,
        WalletReferralSettingsService $settings,
        WalletReferralLeaderboardService $leaderboard,
    ): View {
        $referrals = WhatsappWalletReferral::query()
            ->with(['referrerWallet:id,phone_e164,sender_name', 'referredWallet:id,phone_e164,sender_name'])
            ->orderByDesc('id')
            ->paginate(40, ['*'], 'ref_page')
            ->withQueryString();

        $bonuses = WhatsappWalletReferralBonus::query()
            ->orderByDesc('id')
            ->paginate(40, ['*'], 'bonus_page')
            ->withQueryString();

        $snap = $settings->snapshot();
        $monthStandings = $leaderboard->currentMonthStandings(10);

        return view('admin.whatsapp-wallet.referrals.index', [
            'pageTitle' => 'Referrals',
            'pageSubtitle' => 'Wallet referral attributions, bonuses, and programme settings.',
            'referrals' => $referrals,
            'bonuses' => $bonuses,
            'settings' => $snap,
            'monthStandings' => $monthStandings,
            'stats' => [
                'attributions' => WhatsappWalletReferral::query()->count(),
                'active_windows' => WhatsappWalletReferral::query()->where('bonus_ends_at', '>', now())->count(),
                'bonuses_paid_count' => WhatsappWalletReferralBonus::query()->count(),
                'bonuses_paid_sum' => (float) WhatsappWalletReferralBonus::query()->sum('amount'),
            ],
        ]);
    }

    public function updateSettings(Request $request, WalletReferralSettingsService $settings): RedirectResponse
    {
        $result = $settings->saveFromAdmin($request->all());
        if (! ($result['ok'] ?? false)) {
            return back()
                ->withInput()
                ->withErrors($result['errors'] ?? [])
                ->with('error', $result['message'] ?? 'Could not save settings.');
        }

        return back()->with('success', $result['message'] ?? 'Saved.');
    }
}

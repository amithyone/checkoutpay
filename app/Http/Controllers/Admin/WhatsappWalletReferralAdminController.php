<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappWalletReferral;
use App\Models\WhatsappWalletReferralBonus;
use App\Services\Consumer\WalletReferralLeaderboardService;
use App\Services\Consumer\WalletReferralLaunchNotificationService;
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

    public function notifyLaunch(
        Request $request,
        WalletReferralLaunchNotificationService $launch,
    ): RedirectResponse {
        $validated = $request->validate([
            'dry_run' => ['nullable', 'boolean'],
            'force' => ['nullable', 'boolean'],
            'channel_email' => ['nullable', 'boolean'],
            'channel_push' => ['nullable', 'boolean'],
        ]);

        $dryRun = filter_var($validated['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $force = filter_var($validated['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendEmail = filter_var($validated['channel_email'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $sendPush = filter_var($validated['channel_push'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if (! $sendEmail && ! $sendPush) {
            return back()->with('error', 'Select email and/or app push.');
        }

        try {
            $counts = $launch->sendAll($dryRun, $force, $sendEmail, $sendPush);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $summary = sprintf(
            'Eligible: %d · Emails: %d sent, %d skipped, %d failed · Push: %d sent, %d skipped, %d failed · Marked notified: %d',
            $counts['eligible'],
            $counts['emails_sent'],
            $counts['emails_skipped'],
            $counts['emails_failed'],
            $counts['pushes_sent'],
            $counts['pushes_skipped'],
            $counts['pushes_failed'],
            $counts['marked_notified'],
        );

        return back()->with('success', ($dryRun ? '[Dry run] ' : '').$summary);
    }

    public function launchReach(WalletReferralLaunchNotificationService $launch): RedirectResponse
    {
        try {
            $counts = $launch->estimate(false);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            sprintf(
                'Pending launch reach (not yet notified): %d wallets · ~%d emails · ~%d push tokens',
                $counts['eligible'],
                $counts['emails_sent'],
                $counts['pushes_sent'],
            )
        );
    }
}

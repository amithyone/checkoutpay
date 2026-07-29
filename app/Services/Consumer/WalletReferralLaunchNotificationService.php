<?php

namespace App\Services\Consumer;

use App\Mail\ReferralProgramLaunchMail;
use App\Models\WhatsappWallet;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * One-time (idempotent) email + push telling wallet users about the referral programme.
 */
final class WalletReferralLaunchNotificationService
{
    public function __construct(
        private WalletReferralSettingsService $settings,
        private ConsumerWalletPayCodeService $payCodes,
        private ConsumerWalletPushNotificationService $push,
    ) {}

    /**
     * @return array{
     *   eligible: int,
     *   emails_sent: int,
     *   emails_skipped: int,
     *   emails_failed: int,
     *   pushes_sent: int,
     *   pushes_skipped: int,
     *   pushes_failed: int,
     *   marked_notified: int
     * }
     */
    public function estimate(bool $force = false): array
    {
        $counts = $this->emptyCounts();
        $counts['eligible'] = $this->baseQuery($force)->count();

        $this->baseQuery($force)
            ->orderBy('id')
            ->chunkById(200, function ($wallets) use (&$counts) {
                foreach ($wallets as $wallet) {
                    /** @var WhatsappWallet $wallet */
                    $this->accumulateReach($wallet, $counts);
                }
            });

        return $counts;
    }

    /**
     * @return array{
     *   eligible: int,
     *   emails_sent: int,
     *   emails_skipped: int,
     *   emails_failed: int,
     *   pushes_sent: int,
     *   pushes_skipped: int,
     *   pushes_failed: int,
     *   marked_notified: int
     * }
     */
    public function sendAll(bool $dryRun = false, bool $force = false, bool $sendEmail = true, bool $sendPush = true): array
    {
        if (! $this->settings->enabled()) {
            throw new \RuntimeException('Referrals are disabled. Enable the programme in admin settings first.');
        }

        $counts = $this->emptyCounts();
        $brand = (string) config('whatsapp.bot_brand_name', 'CheckoutNow');
        $title = 'Refer friends & earn';
        $pushBody = 'We now have a referral programme. Open Profile and scroll to Refer and Earn to see your code.';

        $this->baseQuery($force)
            ->orderBy('id')
            ->chunkById(100, function ($wallets) use (
                $dryRun,
                $force,
                $sendEmail,
                $sendPush,
                $brand,
                $title,
                $pushBody,
                &$counts,
            ) {
                foreach ($wallets as $wallet) {
                    /** @var WhatsappWallet $wallet */
                    $counts['eligible']++;
                    $code = $this->payCodes->ensureForWallet($wallet);
                    $name = $wallet->displayName() ?? 'there';
                    $emailSent = false;
                    $pushSent = false;

                    if ($sendEmail) {
                        $email = $wallet->resolveOtpEmail();
                        if ($email === null || $email === '') {
                            $counts['emails_skipped']++;
                        } elseif ($dryRun) {
                            $counts['emails_sent']++;
                            $emailSent = true;
                        } else {
                            try {
                                Mail::to($email)->send(new ReferralProgramLaunchMail($name, $code, $brand));
                                $counts['emails_sent']++;
                                $emailSent = true;
                            } catch (\Throwable $e) {
                                $counts['emails_failed']++;
                                Log::warning('wallet.referral_launch.email_failed', [
                                    'wallet_id' => $wallet->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }

                    if ($sendPush) {
                        if ($dryRun) {
                            $target = $this->pushHasToken($wallet);
                            if ($target) {
                                $counts['pushes_sent']++;
                                $pushSent = true;
                            } else {
                                $counts['pushes_skipped']++;
                            }
                        } else {
                            $result = $this->push->sendReferralLaunch($wallet, $title, $pushBody);
                            if ($result['ok']) {
                                $counts['pushes_sent']++;
                                $pushSent = true;
                            } elseif (str_contains(strtolower($result['message']), 'no mobile push token')) {
                                $counts['pushes_skipped']++;
                            } else {
                                $counts['pushes_failed']++;
                                Log::warning('wallet.referral_launch.push_failed', [
                                    'wallet_id' => $wallet->id,
                                    'message' => $result['message'],
                                ]);
                            }
                        }
                    }

                    $shouldMark = ! $dryRun && ($emailSent || $pushSent);
                    if ($shouldMark && ($wallet->referral_launch_notified_at === null || $force)) {
                        $wallet->referral_launch_notified_at = now();
                        $wallet->save();
                        $counts['marked_notified']++;
                    }
                }
            });

        return $counts;
    }

    /**
     * @param  array{
     *   eligible: int,
     *   emails_sent: int,
     *   emails_skipped: int,
     *   emails_failed: int,
     *   pushes_sent: int,
     *   pushes_skipped: int,
     *   pushes_failed: int,
     *   marked_notified: int
     * }  $counts
     */
    private function accumulateReach(WhatsappWallet $wallet, array &$counts): void
    {
        if ($wallet->resolveOtpEmail()) {
            $counts['emails_sent']++;
        } else {
            $counts['emails_skipped']++;
        }

        if ($this->pushHasToken($wallet)) {
            $counts['pushes_sent']++;
        } else {
            $counts['pushes_skipped']++;
        }
    }

    private function pushHasToken(WhatsappWallet $wallet): bool
    {
        return $this->push->tokenStatus($wallet)['has_token'] ?? false;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<WhatsappWallet>
     */
    private function baseQuery(bool $force)
    {
        return WhatsappWallet::query()
            ->when(! $force, fn ($q) => $q->whereNull('referral_launch_notified_at'));
    }

    /**
     * @return array{
     *   eligible: int,
     *   emails_sent: int,
     *   emails_skipped: int,
     *   emails_failed: int,
     *   pushes_sent: int,
     *   pushes_skipped: int,
     *   pushes_failed: int,
     *   marked_notified: int
     * }
     */
    private function emptyCounts(): array
    {
        return [
            'eligible' => 0,
            'emails_sent' => 0,
            'emails_skipped' => 0,
            'emails_failed' => 0,
            'pushes_sent' => 0,
            'pushes_skipped' => 0,
            'pushes_failed' => 0,
            'marked_notified' => 0,
        ];
    }
}

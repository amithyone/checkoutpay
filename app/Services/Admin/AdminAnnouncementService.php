<?php

namespace App\Services\Admin;

use App\Jobs\SendAdminAnnouncementJob;
use App\Mail\AdminAnnouncementMail;
use App\Models\Admin;
use App\Models\AdminAnnouncement;
use App\Models\Business;
use App\Models\ConsumerWalletApiAccount;
use App\Models\RentalDeviceToken;
use App\Models\Renter;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletPushNotificationService;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Admin compose → email + app push only. Never WhatsApp.
 */
class AdminAnnouncementService
{
    public function __construct(
        private PushNotificationService $push,
        private ConsumerWalletPushNotificationService $walletPush,
    ) {}

    /**
     * @param  list<string>  $audiences
     * @return array{
     *   wallet: array{emails: int, pushes: int},
     *   rentals: array{emails: int, pushes: int},
     *   business: array{emails: int, pushes: int},
     *   totals: array{emails: int, pushes: int}
     * }
     */
    public function estimateReach(array $audiences): array
    {
        $audiences = array_values(array_intersect(AdminAnnouncement::AUDIENCES, $audiences));
        $out = [
            AdminAnnouncement::AUDIENCE_WALLET => ['emails' => 0, 'pushes' => 0],
            AdminAnnouncement::AUDIENCE_RENTALS => ['emails' => 0, 'pushes' => 0],
            AdminAnnouncement::AUDIENCE_BUSINESS => ['emails' => 0, 'pushes' => 0],
            'totals' => ['emails' => 0, 'pushes' => 0],
        ];

        if (in_array(AdminAnnouncement::AUDIENCE_WALLET, $audiences, true) && $this->tableExists('whatsapp_wallets')) {
            $out[AdminAnnouncement::AUDIENCE_WALLET]['emails'] = WhatsappWallet::query()
                ->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNotNull('kyc_email')->where('kyc_email', '!=', '');
                    })->orWhereHas('renter', function ($r) {
                        $r->whereNotNull('email')->where('email', '!=', '');
                    });
                })
                ->count();
            if ($this->tableExists('consumer_wallet_api_accounts')) {
                $out[AdminAnnouncement::AUDIENCE_WALLET]['pushes'] = ConsumerWalletApiAccount::query()
                    ->whereNotNull('fcm_token')
                    ->where('fcm_token', '!=', '')
                    ->whereNotNull('whatsapp_wallet_id')
                    ->count();
            }
        }

        if (in_array(AdminAnnouncement::AUDIENCE_RENTALS, $audiences, true) && $this->tableExists('renters')) {
            $out[AdminAnnouncement::AUDIENCE_RENTALS]['emails'] = Renter::query()
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->count();
            if ($this->tableExists('rental_device_tokens')) {
                $out[AdminAnnouncement::AUDIENCE_RENTALS]['pushes'] = (int) RentalDeviceToken::query()
                    ->whereNotNull('renter_id')
                    ->where('platform', '!=', 'web')
                    ->whereNotNull('token')
                    ->where('token', '!=', '')
                    ->selectRaw('COUNT(DISTINCT renter_id) as c')
                    ->value('c');
            }
        }

        if (in_array(AdminAnnouncement::AUDIENCE_BUSINESS, $audiences, true) && $this->tableExists('businesses')) {
            $out[AdminAnnouncement::AUDIENCE_BUSINESS]['emails'] = Business::query()
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->when(
                    Schema::hasColumn('businesses', 'notifications_email_enabled'),
                    fn ($q) => $q->where(function ($w) {
                        $w->whereNull('notifications_email_enabled')
                            ->orWhere('notifications_email_enabled', true);
                    })
                )
                ->count();
            if ($this->tableExists('rental_device_tokens')) {
                $out[AdminAnnouncement::AUDIENCE_BUSINESS]['pushes'] = (int) RentalDeviceToken::query()
                    ->whereNotNull('business_id')
                    ->where('platform', '!=', 'web')
                    ->whereNotNull('token')
                    ->where('token', '!=', '')
                    ->selectRaw('COUNT(DISTINCT business_id) as c')
                    ->value('c');
            }
        }

        foreach ([AdminAnnouncement::AUDIENCE_WALLET, AdminAnnouncement::AUDIENCE_RENTALS, AdminAnnouncement::AUDIENCE_BUSINESS] as $key) {
            $out['totals']['emails'] += $out[$key]['emails'];
            $out['totals']['pushes'] += $out[$key]['pushes'];
        }

        return $out;
    }

    /**
     * @param  array{
     *   title: string,
     *   body: string,
     *   audiences: list<string>,
     *   channel_email: bool,
     *   channel_push: bool,
     *   push_screen?: ?string
     * }  $data
     */
    public function queue(Admin $admin, array $data): AdminAnnouncement
    {
        $audiences = array_values(array_intersect(AdminAnnouncement::AUDIENCES, $data['audiences'] ?? []));
        if ($audiences === []) {
            throw new \InvalidArgumentException('Select at least one audience.');
        }
        if (empty($data['channel_email']) && empty($data['channel_push'])) {
            throw new \InvalidArgumentException('Select email and/or app push.');
        }

        $estimate = $this->estimateReach($audiences);
        $recipients = 0;
        if (! empty($data['channel_email'])) {
            $recipients += $estimate['totals']['emails'];
        }
        if (! empty($data['channel_push'])) {
            $recipients += $estimate['totals']['pushes'];
        }

        $announcement = AdminAnnouncement::query()->create([
            'admin_id' => $admin->id,
            'title' => trim((string) $data['title']),
            'body' => trim((string) $data['body']),
            'audiences' => $audiences,
            'channel_email' => (bool) ($data['channel_email'] ?? false),
            'channel_push' => (bool) ($data['channel_push'] ?? false),
            'push_screen' => isset($data['push_screen']) && $data['push_screen'] !== ''
                ? (string) $data['push_screen']
                : null,
            'status' => AdminAnnouncement::STATUS_QUEUED,
            'recipients_estimated' => $recipients,
        ]);

        SendAdminAnnouncementJob::dispatch($announcement->id);

        return $announcement;
    }

    /** Run immediately (for sync-less queues or stuck “queued” rows). */
    public function processNow(AdminAnnouncement $announcement): void
    {
        if ($announcement->status === AdminAnnouncement::STATUS_SENT) {
            return;
        }
        if ($announcement->status === AdminAnnouncement::STATUS_SENDING) {
            return;
        }

        $this->process($announcement);
    }

    public function process(AdminAnnouncement $announcement): void
    {
        $claimed = AdminAnnouncement::query()
            ->where('id', $announcement->id)
            ->whereIn('status', [AdminAnnouncement::STATUS_QUEUED, AdminAnnouncement::STATUS_FAILED])
            ->update([
                'status' => AdminAnnouncement::STATUS_SENDING,
                'started_at' => now(),
                'emails_sent' => 0,
                'emails_failed' => 0,
                'emails_skipped' => 0,
                'pushes_sent' => 0,
                'pushes_failed' => 0,
                'pushes_skipped' => 0,
                'error_summary' => null,
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return;
        }

        $announcement->refresh();

        $seenEmails = [];
        $seenPushKeys = [];
        $errors = [];

        try {
            foreach ($announcement->audienceList() as $audience) {
                match ($audience) {
                    AdminAnnouncement::AUDIENCE_WALLET => $this->sendWalletAudience($announcement, $seenEmails, $seenPushKeys, $errors),
                    AdminAnnouncement::AUDIENCE_RENTALS => $this->sendRentalsAudience($announcement, $seenEmails, $seenPushKeys, $errors),
                    AdminAnnouncement::AUDIENCE_BUSINESS => $this->sendBusinessAudience($announcement, $seenEmails, $seenPushKeys, $errors),
                    default => null,
                };
            }

            $announcement->status = AdminAnnouncement::STATUS_SENT;
            $announcement->finished_at = now();
            $announcement->error_summary = $errors === [] ? null : implode("\n", array_slice($errors, 0, 25));
            $announcement->save();
        } catch (\Throwable $e) {
            Log::error('admin_announcement.failed', [
                'id' => $announcement->id,
                'error' => $e->getMessage(),
            ]);
            $announcement->status = AdminAnnouncement::STATUS_FAILED;
            $announcement->finished_at = now();
            $announcement->error_summary = $e->getMessage();
            $announcement->save();
            throw $e;
        }
    }

    /**
     * @param  array<string, true>  $seenEmails
     * @param  array<string, true>  $seenPushKeys
     * @param  list<string>  $errors
     */
    private function sendWalletAudience(AdminAnnouncement $a, array &$seenEmails, array &$seenPushKeys, array &$errors): void
    {
        if (! $this->tableExists('whatsapp_wallets')) {
            return;
        }

        WhatsappWallet::query()
            ->with(['renter:id,email'])
            ->orderBy('id')
            ->chunkById(100, function ($wallets) use ($a, &$seenEmails, &$seenPushKeys, &$errors) {
                foreach ($wallets as $wallet) {
                    /** @var WhatsappWallet $wallet */
                    if ($a->wantsEmail()) {
                        $email = $wallet->resolveOtpEmail();
                        $this->sendEmailOnce($a, $email, $wallet->displayName() ?? 'there', $seenEmails, $errors);
                    }
                    if ($a->wantsPush()) {
                        $key = 'wallet:'.$wallet->id;
                        if (isset($seenPushKeys[$key])) {
                            $a->increment('pushes_skipped');

                            continue;
                        }
                        $seenPushKeys[$key] = true;
                        $result = $this->walletPush->sendAdminMessage(
                            $wallet,
                            $a->title,
                            $a->body,
                            $a->push_screen,
                        );
                        if ($result['ok']) {
                            $a->increment('pushes_sent');
                        } elseif (str_contains(strtolower($result['message']), 'no mobile push token')) {
                            $a->increment('pushes_skipped');
                        } else {
                            $a->increment('pushes_failed');
                            $errors[] = 'wallet#'.$wallet->id.': '.$result['message'];
                        }
                    }
                }
            });
    }

    /**
     * @param  array<string, true>  $seenEmails
     * @param  array<string, true>  $seenPushKeys
     * @param  list<string>  $errors
     */
    private function sendRentalsAudience(AdminAnnouncement $a, array &$seenEmails, array &$seenPushKeys, array &$errors): void
    {
        if (! $this->tableExists('renters')) {
            return;
        }

        Renter::query()->orderBy('id')->chunkById(100, function ($renters) use ($a, &$seenEmails, &$seenPushKeys, &$errors) {
            foreach ($renters as $renter) {
                /** @var Renter $renter */
                if ($a->wantsEmail()) {
                    $email = $this->validEmail($renter->email ?? null);
                    $name = trim((string) ($renter->name ?? '')) ?: 'there';
                    $this->sendEmailOnce($a, $email, $name, $seenEmails, $errors);
                }
                if ($a->wantsPush()) {
                    $this->sendRentalPushOnce(
                        $a,
                        'renter:'.$renter->id,
                        (int) $renter->id,
                        null,
                        $seenPushKeys,
                        $errors,
                    );
                }
            }
        });
    }

    /**
     * @param  array<string, true>  $seenEmails
     * @param  array<string, true>  $seenPushKeys
     * @param  list<string>  $errors
     */
    private function sendBusinessAudience(AdminAnnouncement $a, array &$seenEmails, array &$seenPushKeys, array &$errors): void
    {
        if (! $this->tableExists('businesses')) {
            return;
        }

        Business::query()->orderBy('id')->chunkById(100, function ($businesses) use ($a, &$seenEmails, &$seenPushKeys, &$errors) {
            foreach ($businesses as $business) {
                /** @var Business $business */
                if ($a->wantsEmail()) {
                    if (Schema::hasColumn('businesses', 'notifications_email_enabled')
                        && $business->notifications_email_enabled === false) {
                        $a->increment('emails_skipped');
                    } else {
                        $email = $this->validEmail($business->email ?? null);
                        $name = trim((string) ($business->name ?? '')) ?: 'there';
                        $this->sendEmailOnce($a, $email, $name, $seenEmails, $errors);
                    }
                }
                if ($a->wantsPush()) {
                    $this->sendRentalPushOnce(
                        $a,
                        'business:'.$business->id,
                        null,
                        (int) $business->id,
                        $seenPushKeys,
                        $errors,
                    );
                }
            }
        });
    }

    /**
     * @param  array<string, true>  $seenEmails
     * @param  list<string>  $errors
     */
    private function sendEmailOnce(
        AdminAnnouncement $a,
        ?string $email,
        string $name,
        array &$seenEmails,
        array &$errors,
    ): void {
        if ($email === null) {
            $a->increment('emails_skipped');

            return;
        }
        if (isset($seenEmails[$email])) {
            $a->increment('emails_skipped');

            return;
        }
        $seenEmails[$email] = true;

        try {
            Mail::to($email)->send(new AdminAnnouncementMail($a->title, $a->body, $name));
            $a->increment('emails_sent');
        } catch (\Throwable $e) {
            $a->increment('emails_failed');
            $errors[] = $email.': '.$e->getMessage();
            Log::warning('admin_announcement.email_failed', [
                'announcement_id' => $a->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, true>  $seenPushKeys
     * @param  list<string>  $errors
     */
    private function sendRentalPushOnce(
        AdminAnnouncement $a,
        string $dedupeKey,
        ?int $renterId,
        ?int $businessId,
        array &$seenPushKeys,
        array &$errors,
    ): void {
        if (isset($seenPushKeys[$dedupeKey])) {
            $a->increment('pushes_skipped');

            return;
        }
        $seenPushKeys[$dedupeKey] = true;

        if (! $this->tableExists('rental_device_tokens')) {
            $a->increment('pushes_skipped');

            return;
        }

        $query = RentalDeviceToken::query()
            ->where('platform', '!=', 'web')
            ->whereNotNull('token')
            ->where('token', '!=', '');
        if ($renterId !== null) {
            $query->where('renter_id', $renterId);
        } elseif ($businessId !== null) {
            $query->where('business_id', $businessId);
        } else {
            $a->increment('pushes_skipped');

            return;
        }

        $rows = $query->get(['token', 'platform']);
        if ($rows->isEmpty()) {
            $a->increment('pushes_skipped');

            return;
        }

        try {
            $failed = $this->push->sendToTokens(
                $rows->map(fn ($r) => ['token' => (string) $r->token, 'platform' => $r->platform])->all(),
                $a->title,
                $a->body,
                [
                    'type' => 'admin_announcement',
                    'announcement_id' => (string) $a->id,
                    'screen' => (string) ($a->push_screen ?: 'home'),
                ],
                'rentals_alerts',
                PushNotificationService::PROFILE_RENTALS,
            );
            if ($failed !== [] && count($failed) >= $rows->count()) {
                $a->increment('pushes_failed');
                $errors[] = $dedupeKey.': all device tokens rejected';
            } else {
                $a->increment('pushes_sent');
            }
        } catch (\Throwable $e) {
            $a->increment('pushes_failed');
            $errors[] = $dedupeKey.': '.$e->getMessage();
        }
    }

    private function validEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }
        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}

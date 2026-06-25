<?php

namespace App\Console\Commands;

use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletPushNotificationService;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class DiagnosePushNotifications extends Command
{
    protected $signature = 'push:diagnose
        {--wallet= : Show FCM token status for a whatsapp_wallets.id}';

    protected $description = 'Check Firebase/FCM configuration and consumer push token registration.';

    public function handle(PushNotificationService $push, ConsumerWalletPushNotificationService $consumerPush): int
    {
        $this->line('── CheckoutNow Firebase (wallet app) ──');
        $this->reportProfile(
            PushNotificationService::PROFILE_CHECKOUTNOW,
            'CHECKOUTNOW_FCM_PROJECT_ID',
            'CHECKOUTNOW_FCM_SERVICE_ACCOUNT_JSON',
            'checkout-now-a2b2f',
            $push,
        );
        $this->line('APNs (CheckoutNow iOS): '.($push->isApnsConfigured(PushNotificationService::PROFILE_CHECKOUTNOW) ? 'configured' : 'not configured'));
        $this->line('  CHECKOUTNOW_APNS_KEY_ID / TEAM_ID / PRIVATE_KEY / BUNDLE_ID / ENVIRONMENT');

        $this->newLine();
        $this->line('── Rentals Firebase (ABJ Cam Rentals — separate app) ──');
        $this->reportProfile(
            PushNotificationService::PROFILE_RENTALS,
            'RENTALS_FCM_PROJECT_ID',
            'RENTALS_FCM_SERVICE_ACCOUNT_JSON',
            'abjrentals-ef416',
            $push,
        );

        $tokenCount = ConsumerWalletApiAccount::query()
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->where('fcm_platform', '!=', 'web')
            ->count();
        $this->newLine();
        $this->line('── Consumer app tokens ──');
        $this->line('Registered FCM tokens (all wallets): '.$tokenCount);
        $this->line('credit_push_enabled: '.(config('consumer_wallet.credit_push_enabled', true) ? 'true' : 'false'));

        $walletId = (int) $this->option('wallet');
        if ($walletId > 0) {
            $wallet = WhatsappWallet::query()->find($walletId);
            if (! $wallet) {
                $this->error("Wallet #{$walletId} not found.");

                return self::FAILURE;
            }
            $status = $consumerPush->tokenStatus($wallet);
            $this->line("Wallet #{$walletId} ({$wallet->phone_e164}):");
            $this->line('  checkoutnow_configured: '.($status['configured'] ? 'yes' : 'no'));
            $this->line('  has_token: '.($status['has_token'] ? 'yes' : 'no'));
            $this->line('  platform: '.($status['platform'] ?? '—'));
            $this->line('  delivery_channel: '.($status['delivery_channel'] ?? '—'));
            $this->line('  fcm_configured: '.(($status['fcm_configured'] ?? false) ? 'yes' : 'no'));
            $this->line('  apns_configured: '.(($status['apns_configured'] ?? false) ? 'yes' : 'no'));
            $this->line('  token_updated: '.($status['updated_at'] ?? '—'));
        }

        $this->newLine();
        $this->line('── Notes ──');
        $this->line('google-services.json is for the mobile app build only.');
        $this->line('Server push needs a service account JSON per Firebase project (not google-services.json).');
        $this->line('Admin “Push sent” = FCM/APNs HTTP 2xx. Check laravel.log for: FCM push accepted | APNs push accepted | push send failed');
        $this->line('Test: php artisan push:test --wallet=ID --title="Test" --body="Hello"');

        return self::SUCCESS;
    }

    private function reportProfile(
        string $profile,
        string $projectEnvKey,
        string $serviceAccountEnvKey,
        string $expectedProject,
        PushNotificationService $push,
    ): void {
        $projectId = (string) config("services.firebase.{$profile}.project_id", '');
        $saPath = (string) config("services.firebase.{$profile}.service_account_json", '');
        $saProject = $this->serviceAccountProjectId($saPath);

        $this->line("{$projectEnvKey}: ".($projectId !== '' ? $projectId : '<empty>'));
        $this->line("{$serviceAccountEnvKey}: ".($saPath !== '' ? $saPath : '<empty>'));
        $this->line('Service account project_id: '.($saProject ?? '<missing file>'));
        $this->line('Expected mobile project: '.$expectedProject);
        $this->line('isConfigured(): '.($push->isConfigured($profile) ? 'yes' : 'no'));

        if ($projectId !== '' && $saProject !== null && $projectId !== $saProject) {
            $this->error('MISMATCH: project id and service account must be from the same Firebase project.');
        } elseif ($projectId === $expectedProject && $saProject === $expectedProject) {
            $this->info('Project and service account align.');
        } elseif ($saPath === '' || ! is_file($this->resolvePath($saPath))) {
            $this->warn('Service account file missing — push for this app will not send until you add it.');
        }
    }

    private function serviceAccountProjectId(string $path): ?string
    {
        $resolved = $this->resolvePath($path);
        if ($resolved === null) {
            return null;
        }
        $json = json_decode((string) file_get_contents($resolved), true);

        return is_array($json) && isset($json['project_id']) ? (string) $json['project_id'] : null;
    }

    private function resolvePath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }
        if (is_file($path)) {
            return $path;
        }
        $candidate = base_path($path);

        return is_file($candidate) ? $candidate : null;
    }
}

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
        $projectId = (string) config('services.firebase.project_id', '');
        $saPath = (string) config('services.firebase.service_account_json', '');
        $saProject = $this->serviceAccountProjectId($saPath);

        $this->line('── Firebase / FCM ──');
        $this->line('FCM_PROJECT_ID (.env): '.($projectId !== '' ? $projectId : '<empty>'));
        $this->line('FCM_SERVICE_ACCOUNT_JSON: '.($saPath !== '' ? $saPath : '<empty>'));
        $this->line('Service account project_id: '.($saProject ?? '<unreadable>'));
        $this->line('App mobile Firebase project (expected): checkout-now-a2b2f');

        if ($projectId !== '' && $saProject !== null && $projectId !== $saProject) {
            $this->error('MISMATCH: FCM_PROJECT_ID must match the service account project_id.');
        } elseif ($projectId === 'checkout-now-a2b2f' || $saProject === 'checkout-now-a2b2f') {
            $this->info('Project aligns with CheckoutNow mobile app (checkout-now-a2b2f).');
        } elseif ($projectId !== '' && $saProject !== null) {
            $this->warn('Project may not match the mobile app — tokens from checkout-now-a2b2f will not receive pushes from another Firebase project.');
        }

        $this->line('isConfigured(): '.($push->isConfigured() ? 'yes' : 'no'));
        $this->line('Can obtain FCM access token: '.($this->canObtainAccessToken($saPath) ? 'yes' : 'no'));
        $this->line('credit_push_enabled: '.(config('consumer_wallet.credit_push_enabled', true) ? 'true' : 'false'));

        $tokenCount = ConsumerWalletApiAccount::query()
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->where('fcm_platform', '!=', 'web')
            ->count();
        $this->newLine();
        $this->line('── Consumer app tokens ──');
        $this->line('Registered FCM tokens (all wallets): '.$tokenCount);

        $walletId = (int) $this->option('wallet');
        if ($walletId > 0) {
            $wallet = WhatsappWallet::query()->find($walletId);
            if (! $wallet) {
                $this->error("Wallet #{$walletId} not found.");

                return self::FAILURE;
            }
            $status = $consumerPush->tokenStatus($wallet);
            $this->line("Wallet #{$walletId} ({$wallet->phone_e164}):");
            $this->line('  has_token: '.($status['has_token'] ? 'yes' : 'no'));
            $this->line('  platform: '.($status['platform'] ?? '—'));
            $this->line('  token_updated: '.($status['updated_at'] ?? '—'));
        }

        $this->newLine();
        $this->line('── How to interpret admin “Push sent” ──');
        $this->line('Success = FCM HTTP API accepted the message (2xx).');
        $this->line('It does NOT guarantee the phone displayed it (permissions, wrong Firebase project, iOS APNs missing, app killed battery saver, etc.).');
        $this->line('Check storage/logs/laravel.log for lines: FCM push accepted | FCM push send failed');
        $this->line('Test send: php artisan push:test --wallet=ID --title="Test" --body="Hello"');

        return self::SUCCESS;
    }

    private function serviceAccountProjectId(string $path): ?string
    {
        if ($path === '') {
            return null;
        }
        $resolved = $path;
        if (! is_file($resolved)) {
            $candidate = base_path($path);
            if (is_file($candidate)) {
                $resolved = $candidate;
            } else {
                return null;
            }
        }
        $json = json_decode((string) file_get_contents($resolved), true);

        return is_array($json) && isset($json['project_id']) ? (string) $json['project_id'] : null;
    }

    private function canObtainAccessToken(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        try {
            $push = app(PushNotificationService::class);

            return $push->isConfigured();
        } catch (\Throwable) {
            return false;
        }
    }
}

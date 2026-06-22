<?php

namespace App\Console\Commands;

use App\Models\ConsumerWalletApiAccount;
use App\Models\RentalDeviceToken;
use App\Models\WhatsappWallet;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class SendTestPushNotification extends Command
{
    protected $signature = 'push:test
        {--token= : Send to a specific device token}
        {--renter= : Send to all native tokens for a renter id}
        {--business= : Send to all native tokens for a business id}
        {--wallet= : Send to FCM token for a consumer wallet id (whatsapp_wallets.id)}
        {--title=Test notification : Push title}
        {--body=Push setup is working. : Push body}
        {--type=test_push : data.type payload value}
        {--screen= : Optional data.screen (e.g. history for money alerts)}';

    protected $description = 'Send a test FCM push notification to token(s).';

    public function handle(PushNotificationService $push): int
    {
        $tokens = [];
        $single = trim((string) $this->option('token'));
        $renterId = (int) $this->option('renter');
        $businessId = (int) $this->option('business');
        $walletId = (int) $this->option('wallet');

        if ($single !== '') {
            $tokens[] = $single;
        }

        if ($walletId > 0) {
            $account = ConsumerWalletApiAccount::query()
                ->where('whatsapp_wallet_id', $walletId)
                ->whereNotNull('fcm_token')
                ->where('fcm_token', '!=', '')
                ->where('fcm_platform', '!=', 'web')
                ->orderByDesc('fcm_token_updated_at')
                ->first();
            if ($account) {
                $tokens[] = (string) $account->fcm_token;
            }
        }

        if ($renterId > 0) {
            $tokens = array_merge($tokens, RentalDeviceToken::query()
                ->where('renter_id', $renterId)
                ->where('platform', '!=', 'web')
                ->pluck('token')
                ->all());
        }

        if ($businessId > 0) {
            $tokens = array_merge($tokens, RentalDeviceToken::query()
                ->where('business_id', $businessId)
                ->where('platform', '!=', 'web')
                ->pluck('token')
                ->all());
        }

        $tokens = array_values(array_unique(array_filter($tokens)));
        if (count($tokens) < 1) {
            $this->error('No target token found. Use --token, --wallet, --renter, or --business.');

            return self::FAILURE;
        }

        $profile = $walletId > 0
            ? PushNotificationService::PROFILE_CHECKOUTNOW
            : PushNotificationService::PROFILE_RENTALS;

        if (! $push->isConfigured($profile)) {
            $envHint = $profile === PushNotificationService::PROFILE_CHECKOUTNOW
                ? 'CHECKOUTNOW_FCM_PROJECT_ID and CHECKOUTNOW_FCM_SERVICE_ACCOUNT_JSON'
                : 'RENTALS_FCM_PROJECT_ID and RENTALS_FCM_SERVICE_ACCOUNT_JSON';
            $this->error("Missing Firebase config for {$profile}. Set {$envHint}.");

            return self::FAILURE;
        }

        $title = (string) $this->option('title');
        $body = (string) $this->option('body');
        $type = (string) $this->option('type');
        $screen = trim((string) $this->option('screen'));

        $data = ['type' => $type];
        if ($screen !== '') {
            $data['screen'] = $screen;
        }

        $channel = $profile === PushNotificationService::PROFILE_CHECKOUTNOW
            ? (string) config('consumer_wallet.credit_push_channel', 'money_received')
            : 'rentals_alerts';

        $push->sendToTokens($tokens, $title, $body, $data, $channel, $profile);

        $this->info('Push request sent to FCM.');
        $this->line('Profile: '.$profile);
        $this->line('Tokens targeted: '.count($tokens));
        $this->line('Title: '.$title);
        $this->line('Body: '.$body);

        return self::SUCCESS;
    }
}

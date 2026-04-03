<?php

namespace App\Console\Commands;

use App\Models\RentalDeviceToken;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class SendTestPushNotification extends Command
{
    protected $signature = 'push:test
        {--token= : Send to a specific device token}
        {--renter= : Send to all native tokens for a renter id}
        {--business= : Send to all native tokens for a business id}
        {--title=Test notification : Push title}
        {--body=Push setup is working. : Push body}
        {--type=test_push : data.type payload value}';

    protected $description = 'Send a test FCM push notification to token(s).';

    public function handle(PushNotificationService $push): int
    {
        $projectId = (string) config('services.firebase.project_id', '');
        $serviceAccount = (string) config('services.firebase.service_account_json', '');
        if ($projectId === '' || $serviceAccount === '') {
            $this->error('Missing Firebase config. Set FCM_PROJECT_ID and FCM_SERVICE_ACCOUNT_JSON (file path or single-line JSON; never multi-line JSON in .env).');
            return self::FAILURE;
        }

        $tokens = [];
        $single = trim((string) $this->option('token'));
        $renterId = (int) $this->option('renter');
        $businessId = (int) $this->option('business');

        if ($single !== '') {
            $tokens[] = $single;
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
            $this->error('No target token found. Use --token or --renter or --business.');
            return self::FAILURE;
        }

        $title = (string) $this->option('title');
        $body = (string) $this->option('body');
        $type = (string) $this->option('type');

        $push->sendToTokens($tokens, $title, $body, ['type' => $type]);

        $this->info('Push request sent to FCM.');
        $this->line('Tokens targeted: '.count($tokens));
        $this->line('Title: '.$title);
        $this->line('Body: '.$body);

        return self::SUCCESS;
    }
}


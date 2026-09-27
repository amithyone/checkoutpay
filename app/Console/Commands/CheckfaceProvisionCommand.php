<?php

namespace App\Console\Commands;

use App\Services\Checkface\CheckfaceClient;
use App\Services\Checkface\CheckfaceProvisionService;
use Illuminate\Console\Command;

class CheckfaceProvisionCommand extends Command
{
    protected $signature = 'checkface:provision
                            {--email= : First-party email (@check-outnow.com or @check-outpay.com)}
                            {--password= : Account password (generated if omitted)}
                            {--show-key : Print the API key once in the console}
                            {--no-persist : Do not write CHECKFACE_API_TOKEN to the secrets file}';

    protected $description = 'Create/login a CheckFace first-party account and store an API key for checkout wallet face checks';

    public function handle(CheckfaceProvisionService $provision, CheckfaceClient $client): int
    {
        if ($client->isConfigured()) {
            $this->info('CheckFace is already configured (CHECKFACE_API_TOKEN is set).');
            if (! $this->confirm('Issue a new API key for the provision email anyway?', false)) {
                return self::SUCCESS;
            }
        }

        $result = $provision->provision(
            $this->option('email') ? (string) $this->option('email') : null,
            $this->option('password') ? (string) $this->option('password') : null,
            ! (bool) $this->option('no-persist'),
        );

        if (! ($result['ok'] ?? false)) {
            $this->error($result['message'] ?? 'Provisioning failed.');

            return self::FAILURE;
        }

        $this->info($result['message']);
        $this->line('email: '.($result['email'] ?? ''));
        $this->line('account_id: '.($result['account_id'] ?? ''));
        $this->line('created: '.(($result['created'] ?? false) ? 'yes' : 'no (re-keyed existing)'));
        $this->line('persisted: '.(($result['persisted'] ?? false) ? 'yes' : 'no'));

        if ($this->option('show-key') && ! empty($result['api_key'])) {
            $this->warn('API key (copy now): '.$result['api_key']);
        }

        return self::SUCCESS;
    }
}

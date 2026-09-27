<?php

namespace App\Console\Commands;

use App\Services\Partner\PartnerLicenseClient;
use Illuminate\Console\Command;

class PartnerLicensePingCommand extends Command
{
    protected $signature = 'partner:license-ping {--force : Bypass local cache and phone home}';

    protected $description = 'Verify partner license with the issuer (partner deployments only)';

    public function handle(PartnerLicenseClient $client): int
    {
        if (! $client->isEnforced()) {
            $this->info('Partner license enforcement is disabled on this server.');

            return self::SUCCESS;
        }

        $result = $client->ping((bool) $this->option('force'));

        $this->line('Status: '.($result['status'] ?? 'unknown'));
        if (! empty($result['message'])) {
            $this->line('Message: '.$result['message']);
        }

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}

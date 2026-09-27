<?php

namespace App\Console\Commands;

use App\Services\Partner\PartnerLicenseClient;
use Illuminate\Console\Command;

class PartnerVerifyBuildCommand extends Command
{
    protected $signature = 'partner:verify-build';

    protected $description = 'Verify partner-build-manifest.json and encoded file checksums';

    public function handle(PartnerLicenseClient $client): int
    {
        $result = $client->verifyBuildManifest();

        $this->line($result['message']);
        if (! empty($result['missing'])) {
            foreach ($result['missing'] as $path) {
                $this->warn(' - '.$path);
            }
        }

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}

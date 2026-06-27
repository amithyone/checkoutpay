<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncAndroidAssetLinksCommand extends Command
{
    protected $signature = 'consumer:sync-android-assetlinks';

    protected $description = 'Write public/.well-known/assetlinks.json from CONSUMER_ANDROID_ASSETLINKS_SHA256';

    public function handle(): int
    {
        $fingerprints = (array) config('consumer_wallet.android_assetlinks_sha256_fingerprints', []);
        $fingerprints = array_values(array_filter(array_map(
            static fn ($fp) => trim((string) $fp),
            $fingerprints,
        )));

        if ($fingerprints === []) {
            $this->error('Set CONSUMER_ANDROID_ASSETLINKS_SHA256 in .env (colon-separated hex fingerprints).');

            return self::FAILURE;
        }

        $payload = [[
            'relation' => [
                'delegate_permission/common.handle_all_urls',
                'delegate_permission/common.get_login_creds',
            ],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => 'com.checkoutnow.app',
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]];

        $path = public_path('.well-known/assetlinks.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->info('Updated '.$path.' with '.count($fingerprints).' fingerprint(s).');

        return self::SUCCESS;
    }
}

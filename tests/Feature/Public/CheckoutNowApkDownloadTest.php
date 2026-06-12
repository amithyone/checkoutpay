<?php

namespace Tests\Feature\Public;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CheckoutNowApkDownloadTest extends TestCase
{
    private ?string $tempApkPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Cached production config uses database sessions; avoid sqlite "sessions" table in feature tests.
        config(['session.driver' => 'array']);
    }

    protected function tearDown(): void
    {
        if ($this->tempApkPath !== null && File::exists($this->tempApkPath)) {
            File::delete($this->tempApkPath);
        }

        parent::tearDown();
    }

    public function test_apk_download_returns_attachment_with_correct_filename(): void
    {
        $this->tempApkPath = storage_path('framework/testing-checkoutnow.apk');
        File::put($this->tempApkPath, 'fake-apk-binary');

        config([
            'whatsapp.wallet_android_apk_path' => $this->tempApkPath,
        ]);

        $response = $this->get('/download/checkoutnow-android.apk');

        $response->assertOk();
        $response->assertDownload('checkoutnow-android.apk');
        $response->assertHeader('content-type', 'application/vnd.android.package-archive');
        $this->assertSame((string) strlen('fake-apk-binary'), $response->headers->get('Content-Length'));
    }

    public function test_apk_download_returns_404_when_file_missing(): void
    {
        config([
            'whatsapp.wallet_android_apk_path' => storage_path('framework/missing-checkoutnow.apk'),
        ]);

        $this->get('/download/checkoutnow-android.apk')->assertNotFound();
    }
}

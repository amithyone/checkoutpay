<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\CheckoutNowApp;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CheckoutNowApkDownloadController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $path = CheckoutNowApp::androidApkPath();

        if (! is_readable($path)) {
            abort(404, 'CheckoutNow Android app is not available for download yet. Please try again shortly.');
        }

        return response()->download($path, 'checkoutnow-android.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
}

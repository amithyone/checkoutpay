<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QRCodeService
{
    /**
     * Generate QR code data URL
     */
    public function generate(string $data): string
    {
        // Generate QR code as base64 data URL
        $qrCode = QrCode::format('png')
            ->size(200)
            ->margin(1)
            ->generate($data);

        return 'data:image/png;base64,' . base64_encode($qrCode);
    }

    /**
     * Generate QR code and save to file
     */
    public function generateToFile(string $data, string $path): string
    {
        QrCode::format('png')
            ->size(200)
            ->margin(1)
            ->generate($data, storage_path('app/public/' . $path));

        return $path;
    }
}

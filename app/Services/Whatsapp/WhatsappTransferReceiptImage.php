<?php

namespace App\Services\Whatsapp;

/**
 * Builds a small PNG receipt (no wallet balance) for WhatsApp sendMedia. Requires GD extension.
 */
final class WhatsappTransferReceiptImage
{
    private const WIDTH = 420;

    private const HEIGHT = 340;

    /**
     * @return non-empty-string|null Raw PNG bytes (not base64), or null if GD unavailable / render failed
     */
    public static function bankTransferPngBytes(
        string $brand,
        string $beneficiary,
        string $bankName,
        string $accountLast4,
        float $amount,
        string $reference,
        string $whenLine,
    ): ?string {
        $lines = [
            'BANK TRANSFER — SUCCESS',
            'Amount: NGN '.number_format($amount, 2),
            'To: '.self::foldForReceipt($beneficiary, 44),
            'Bank: '.self::foldForReceipt($bankName, 44),
            'Account: ****'.$accountLast4,
            'Ref: '.self::foldForReceipt($reference, 44),
            'Time: '.self::foldForReceipt($whenLine, 44),
            '',
            self::foldForReceipt($brand, 44),
        ];

        return self::renderPng($lines);
    }

    /**
     * @return non-empty-string|null
     */
    public static function p2pSentPngBytes(
        string $brand,
        string $toMasked,
        float $amount,
        string $whenLine,
        string $receiptId,
        string $debitCurrency = 'NGN',
        ?string $recipientCreditLine = null,
    ): ?string {
        $debitLine = 'Amount: '.strtoupper($debitCurrency).' '.number_format($amount, 2);
        $lines = [
            'WHATSAPP WALLET SEND — SUCCESS',
            $debitLine,
        ];
        if ($recipientCreditLine !== null && $recipientCreditLine !== '') {
            $lines[] = self::foldForReceipt($recipientCreditLine, 44);
        }
        $lines[] = 'To: '.self::foldForReceipt($toMasked, 44);
        $lines[] = 'Receipt: '.self::foldForReceipt($receiptId, 44);
        $lines[] = 'Time: '.self::foldForReceipt($whenLine, 44);
        $lines[] = '';
        $lines[] = self::foldForReceipt($brand, 44);

        return self::renderPng($lines);
    }

    /**
     * @param  list<string>  $lines
     * @return non-empty-string|null
     */
    private static function renderPng(array $lines): ?string
    {
        if (! extension_loaded('gd') || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($im === false) {
            return null;
        }

        $bg = imagecolorallocate($im, 250, 250, 252);
        $fg = imagecolorallocate($im, 30, 41, 59);
        $muted = imagecolorallocate($im, 100, 116, 139);
        $accent = imagecolorallocate($im, 22, 163, 74);
        imagefilledrectangle($im, 0, 0, self::WIDTH, self::HEIGHT, $bg);

        imagefilledellipse($im, self::WIDTH - 48, 48, 56, 56, $accent);
        $tick = imagecolorallocate($im, 255, 255, 255);
        imageline($im, self::WIDTH - 58, 48, self::WIDTH - 46, 60, $tick);
        imageline($im, self::WIDTH - 46, 60, self::WIDTH - 28, 38, $tick);

        $font = self::resolveTtfPath();
        $y = 28;
        $ttfLine = 24;

        foreach ($lines as $i => $line) {
            $line = trim($line);
            if ($line === '') {
                $y += 10;

                continue;
            }
            $color = ($i === 0) ? $accent : (($i === count($lines) - 1) ? $muted : $fg);
            if ($font !== null && function_exists('imagettftext')) {
                @imagettftext($im, 11, 0, 20, $y, $color, $font, $line);
                $y += $ttfLine;
            } else {
                $map = self::splitForBitmapFont($line, 50);
                foreach ($map as $j => $chunk) {
                    imagestring($im, 3, 20, $y + $j * 14, $chunk, $color);
                }
                $y += max(18, count($map) * 14);
            }
            if ($y > self::HEIGHT - 24) {
                break;
            }
        }

        ob_start();
        $ok = imagepng($im, null, 6);
        imagedestroy($im);
        $raw = ob_get_clean();

        if ($ok !== true || $raw === false || $raw === '') {
            return null;
        }

        return $raw;
    }

    private static function resolveTtfPath(): ?string
    {
        $configured = trim((string) config('whatsapp.wallet.receipt_font_path', ''));
        if ($configured !== '' && is_readable($configured)) {
            return $configured;
        }
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
        ];
        foreach ($candidates as $p) {
            if (is_readable($p)) {
                return $p;
            }
        }

        return null;
    }

    private static function foldForReceipt(string $text, int $maxLen): string
    {
        $t = trim($text);
        if (mb_strlen($t) <= $maxLen) {
            return self::asciiFold($t);
        }

        return self::asciiFold(mb_substr($t, 0, $maxLen - 1).'…');
    }

    private static function asciiFold(string $text): string
    {
        if ($text === '') {
            return '';
        }
        if (function_exists('iconv')) {
            $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($c !== false && $c !== '') {
                return $c;
            }
        }

        return preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
    }

    /**
     * @return list<string>
     */
    private static function splitForBitmapFont(string $line, int $chunkLen): array
    {
        $line = self::asciiFold($line);
        if ($line === '') {
            return [''];
        }
        $out = [];
        $len = strlen($line);
        for ($i = 0; $i < $len; $i += $chunkLen) {
            $out[] = substr($line, $i, $chunkLen);
        }

        return $out;
    }
}

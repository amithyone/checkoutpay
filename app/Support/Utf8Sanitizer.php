<?php

namespace App\Support;

final class Utf8Sanitizer
{
    public static function clean(?string $string): ?string
    {
        if ($string === null || $string === '') {
            return $string;
        }

        if (! mb_check_encoding($string, 'UTF-8')) {
            foreach (['ISO-8859-1', 'Windows-1252', 'UTF-8'] as $encoding) {
                $converted = @mb_convert_encoding($string, 'UTF-8', $encoding);
                if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                    $string = $converted;
                    break;
                }
            }
        }

        $sanitized = @iconv('UTF-8', 'UTF-8//IGNORE', $string);
        if ($sanitized === false || ! mb_check_encoding($sanitized, 'UTF-8')) {
            $sanitized = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        }

        $sanitized = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/u', '', $sanitized) ?? '';

        if (! mb_check_encoding($sanitized, 'UTF-8')) {
            $sanitized = mb_convert_encoding($sanitized, 'UTF-8', 'UTF-8');
        }

        return $sanitized !== '' ? $sanitized : '';
    }
}

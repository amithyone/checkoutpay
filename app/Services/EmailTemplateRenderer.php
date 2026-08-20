<?php

namespace App\Services;

/**
 * Safe HTML email template renderer — substitution only, never Blade/PHP execution.
 */
class EmailTemplateRenderer
{
    /**
     * Patterns that must never appear in admin-editable custom templates.
     *
     * @var list<string>
     */
    private const FORBIDDEN_PATTERNS = [
        '/@php\b/i',
        '/@endphp\b/i',
        '/@include\b/i',
        '/@extends\b/i',
        '/@section\b/i',
        '/@yield\b/i',
        '/@foreach\b/i',
        '/@if\b/i',
        '/@while\b/i',
        '/@for\b/i',
        '/@csrf\b/i',
        '/<\?php/i',
        '/<\?=/i',
        '/\beval\s*\(/i',
        '/\bassert\s*\(/i',
        '/\bbase64_decode\s*\(/i',
        '/\bfile_put_contents\s*\(/i',
        '/\bfile_get_contents\s*\(/i',
        '/\bsystem\s*\(/i',
        '/\bexec\s*\(/i',
        '/\bshell_exec\s*\(/i',
        '/\bpassthru\s*\(/i',
        '/\bproc_open\s*\(/i',
        '/\bpreg_replace\s*\([^)]*\/e/i',
        '/\bcreate_function\s*\(/i',
        '/\$_(GET|POST|REQUEST|SERVER|COOKIE|FILES|ENV)\b/i',
    ];

    public static function containsForbiddenSyntax(string $html): bool
    {
        foreach (self::FORBIDDEN_PATTERNS as $pattern) {
            if (preg_match($pattern, $html) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Replace {{ $var }} / {{ $object->prop }} / {{ $object.prop }} with escaped values.
     *
     * @param  array<string, mixed>  $data
     */
    public static function render(string $html, array $data): string
    {
        if (self::containsForbiddenSyntax($html)) {
            throw new \InvalidArgumentException('Template contains forbidden syntax.');
        }

        return (string) preg_replace_callback(
            '/\{\{\s*\$([a-zA-Z_][a-zA-Z0-9_]*(?:(?:->|\.)[a-zA-Z_][a-zA-Z0-9_]*)*)\s*\}\}/',
            function (array $matches) use ($data) {
                $path = str_replace('->', '.', $matches[1]);
                $value = data_get($data, $path);

                if ($value === null) {
                    return '';
                }

                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }

                if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                    return e((string) $value);
                }

                return '';
            },
            $html
        );
    }
}

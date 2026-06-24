<?php

namespace Tests\Unit\Support;

use App\Support\Utf8Sanitizer;
use PHPUnit\Framework\TestCase;

class Utf8SanitizerTest extends TestCase
{
    public function test_converts_latin1_copyright_to_valid_utf8(): void
    {
        $latin1 = "\xA9 Moniepoint";

        $clean = Utf8Sanitizer::clean($latin1);

        $this->assertSame('© Moniepoint', $clean);
        $this->assertTrue(mb_check_encoding($clean, 'UTF-8'));
    }

    public function test_preserves_valid_utf8(): void
    {
        $value = 'Payment received ₦1,000';

        $this->assertSame($value, Utf8Sanitizer::clean($value));
    }

    public function test_null_and_empty_are_unchanged(): void
    {
        $this->assertNull(Utf8Sanitizer::clean(null));
        $this->assertSame('', Utf8Sanitizer::clean(''));
    }
}

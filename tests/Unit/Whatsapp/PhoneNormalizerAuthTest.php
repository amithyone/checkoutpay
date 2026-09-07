<?php

namespace Tests\Unit\Whatsapp;

use App\Services\Whatsapp\PhoneNormalizer;
use Tests\TestCase;

class PhoneNormalizerAuthTest extends TestCase
{
    public function test_uk_international_without_plus_is_not_nigeria(): void
    {
        $this->assertSame('447776291794', PhoneNormalizer::canonicalAuthE164Digits('447776291794'));
        $this->assertSame('447776291794', PhoneNormalizer::canonicalAuthE164Digits('+447776291794'));
        $this->assertSame('GB', PhoneNormalizer::countryIsoFromE164('447776291794'));
    }

    public function test_uk_local_07_is_not_treated_as_nigeria(): void
    {
        $this->assertNull(PhoneNormalizer::canonicalNgE164Digits('07776291794'));
        $this->assertSame('447776291794', PhoneNormalizer::canonicalAuthE164Digits('07776291794'));
        $this->assertSame('447776291794', PhoneNormalizer::canonicalGbE164Digits('07776291794'));
    }

    public function test_uk_country_hint_composes_local_or_national(): void
    {
        $this->assertSame('447776291794', PhoneNormalizer::canonicalAuthE164Digits('07776291794', 'GB'));
        $this->assertSame('447776291794', PhoneNormalizer::canonicalAuthE164Digits('7776291794', 'GB'));
        $this->assertSame('447776291794', PhoneNormalizer::canonicalAuthE164Digits('4407776291794', 'GB'));
    }

    public function test_truncated_uk_international_is_rejected_without_wallet_lookup(): void
    {
        $this->assertNull(PhoneNormalizer::canonicalAuthE164Digits('44777629179'));
        $this->assertNull(PhoneNormalizer::canonicalGbE164Digits('44777629179'));
    }

    public function test_nigeria_local_still_works(): void
    {
        $this->assertSame('2348012345678', PhoneNormalizer::canonicalAuthE164Digits('08012345678'));
        $this->assertSame('2348012345678', PhoneNormalizer::canonicalAuthE164Digits('8012345678'));
        $this->assertSame('2348012345678', PhoneNormalizer::canonicalAuthE164Digits('2348012345678'));
    }

    public function test_namibia_081_local_still_works(): void
    {
        $this->assertSame('264818612179', PhoneNormalizer::canonicalAuthE164Digits('0818612179'));
        $this->assertSame('264818612179', PhoneNormalizer::canonicalAuthE164Digits('264818612179'));
    }

    public function test_kenya_explicit_code_still_works(): void
    {
        $this->assertSame('254712345678', PhoneNormalizer::canonicalAuthE164Digits('+254712345678'));
        $this->assertSame('254712345678', PhoneNormalizer::canonicalAuthE164Digits('0712345678'));
    }
}

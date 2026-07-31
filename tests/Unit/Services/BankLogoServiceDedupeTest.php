<?php

namespace Tests\Unit\Services;

use App\Services\BankLogoService;
use ReflectionMethod;
use Tests\TestCase;

class BankLogoServiceDedupeTest extends TestCase
{
    /**
     * @param  list<array{code: string, name: string, logo_url: string|null}>  $rows
     * @return list<array{code: string, name: string, logo_url: string|null}>
     */
    private function dedupe(array $rows): array
    {
        $method = new ReflectionMethod(BankLogoService::class, 'dedupeApiBankRows');
        $method->setAccessible(true);

        return $method->invoke(app(BankLogoService::class), $rows);
    }

    public function test_dedupe_merges_legacy_and_nip_codes(): void
    {
        $banks = $this->dedupe([
            ['code' => '044', 'name' => 'ACCESS BANK', 'logo_url' => null],
            ['code' => '000014', 'name' => 'Access Bank', 'logo_url' => null],
            ['code' => '058', 'name' => 'GTBank', 'logo_url' => null],
            ['code' => '000013', 'name' => 'Guaranty Trust Bank', 'logo_url' => null],
        ]);

        $codes = array_column($banks, 'code');

        $this->assertCount(2, $banks);
        $this->assertContains('000014', $codes);
        $this->assertContains('000013', $codes);
        $this->assertNotContains('044', $codes);
        $this->assertNotContains('058', $codes);
    }

    public function test_dedupe_merges_same_institution_with_different_nip_codes(): void
    {
        $banks = $this->dedupe([
            ['code' => '000005', 'name' => 'ACCESS BANK', 'logo_url' => null],
            ['code' => '000014', 'name' => 'Access Bank', 'logo_url' => null],
        ]);

        $this->assertCount(1, $banks);
        $this->assertSame('000014', $banks[0]['code']);
        $this->assertSame('Access Bank', $banks[0]['name']);
    }

    public function test_dedupe_prefers_row_with_logo(): void
    {
        $banks = $this->dedupe([
            ['code' => '044', 'name' => 'ACCESS BANK', 'logo_url' => null],
            ['code' => '000014', 'name' => 'Access Bank', 'logo_url' => 'https://example.test/000014.svg'],
        ]);

        $this->assertCount(1, $banks);
        $this->assertSame('000014', $banks[0]['code']);
        $this->assertSame('https://example.test/000014.svg', $banks[0]['logo_url']);
    }
}

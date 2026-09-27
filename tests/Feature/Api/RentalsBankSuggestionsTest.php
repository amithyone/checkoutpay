<?php

namespace Tests\Feature\Api;

use App\Models\Bank;
use App\Models\BankAccountPrefixRule;
use App\Services\BankLogoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RentalsBankSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bank_account_prefixes.rules' => []]);

        app(BankLogoService::class)->forgetListCache();
        Cache::forget(BankAccountPrefixRule::cacheKey());
    }

    private function seedRule(string $prefix, string $code, string $name): void
    {
        BankAccountPrefixRule::updateOrCreate(
            ['prefix' => $prefix, 'bank_code' => $code],
            [
                'bank_name' => $name,
                'is_active' => true,
            ]
        );
        Cache::forget(BankAccountPrefixRule::cacheKey());
    }

    public function test_returns_rules_catalog_without_account(): void
    {
        $this->seedRule('802', '100004', 'OPay');
        $this->seedRule('802', '100033', 'PalmPay');

        $response = $this->getJson('/api/v1/rentals/banks/suggestions')
            ->assertOk()
            ->assertJsonPath('success', true);

        $suggestions = $response->json('data.suggestions');
        $this->assertIsArray($suggestions);
        $this->assertNotEmpty($suggestions);
        $this->assertArrayHasKey('updated_at', $response->json('data'));

        $opay = collect($suggestions)->firstWhere('name', 'OPay');
        $this->assertNotNull($opay);
        $this->assertContains('802', $opay['prefixes']);
        $this->assertNotEmpty($opay['codes']);
    }

    public function test_rejects_account_shorter_than_two_digits(): void
    {
        $this->getJson('/api/v1/rentals/banks/suggestions?account=8')
            ->assertStatus(422);
    }

    public function test_returns_empty_banks_when_no_prefix_match(): void
    {
        $this->getJson('/api/v1/rentals/banks/suggestions?account=123456')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.banks', []);
    }

    public function test_returns_multiple_banks_for_shared_prefix(): void
    {
        $this->seedRule('814', '100004', 'OPay');
        $this->seedRule('814', '100033', 'PalmPay');

        Bank::updateOrCreate(['code' => '100004'], ['name' => 'OPay']);
        Bank::updateOrCreate(['code' => '100033'], ['name' => 'PalmPay']);
        Cache::forget(app(BankLogoService::class)->cacheKey());

        $response = $this->getJson('/api/v1/rentals/banks/suggestions?account=8148790554')
            ->assertOk()
            ->assertJsonPath('success', true);

        $banks = $response->json('data.banks');
        $this->assertCount(2, $banks);
        $codes = collect($banks)->pluck('code')->all();
        $this->assertContains('100004', $codes);
        $this->assertContains('100033', $codes);
    }

    public function test_returns_suggested_banks_for_matching_prefix(): void
    {
        $this->seedRule('802', '100004', 'OPay');

        Bank::updateOrCreate(
            ['code' => '100004'],
            ['name' => 'OPay'],
        );

        Cache::forget(app(BankLogoService::class)->cacheKey());

        $response = $this->getJson('/api/v1/rentals/banks/suggestions?account=8021234567')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.banks.0.code', '100004');

        $this->assertStringContainsString('opay', strtolower((string) $response->json('data.banks.0.name')));
    }

    public function test_strips_non_digits_from_account_input(): void
    {
        $this->seedRule('855', '100033', 'PalmPay');

        Bank::updateOrCreate(
            ['code' => '100033'],
            ['name' => 'PalmPay'],
        );

        Cache::forget(app(BankLogoService::class)->cacheKey());

        $response = $this->getJson('/api/v1/rentals/banks/suggestions?account=855-123-4567')
            ->assertOk()
            ->assertJsonPath('data.banks.0.code', '100033');

        $this->assertStringContainsString('palm', strtolower((string) $response->json('data.banks.0.name')));
    }
}

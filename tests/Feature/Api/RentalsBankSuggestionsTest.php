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
            ['prefix' => $prefix],
            [
                'bank_code' => $code,
                'bank_name' => $name,
                'is_active' => true,
            ]
        );
        Cache::forget(BankAccountPrefixRule::cacheKey());
    }

    public function test_requires_account_query(): void
    {
        $this->getJson('/api/v1/rentals/banks/suggestions')
            ->assertStatus(422);
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

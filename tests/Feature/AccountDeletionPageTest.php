<?php

namespace Tests\Feature;

use Database\Seeders\LegalPagesDefinitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountDeletionPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        LegalPagesDefinitions::syncToDatabase();
    }

    #[Test]
    public function account_deletion_page_loads_with_request_instructions(): void
    {
        $response = $this->get('/account-deletion');

        $response->assertOk();
        $response->assertSee('Request account deletion', false);
        $response->assertSee('Email deletion request', false);
        $response->assertSee('What we delete', false);
        $response->assertSee('What we may keep', false);
    }

    #[Test]
    public function privacy_policy_links_to_account_deletion_page(): void
    {
        $response = $this->get('/privacy-policy');

        $response->assertOk();
        $response->assertSee('/account-deletion', false);
        $response->assertSee('account and data deletion', false);
    }
}

<?php

namespace Tests\Feature;

use Database\Seeders\LegalPagesDefinitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        LegalPagesDefinitions::syncToDatabase();
    }

    #[Test]
    public function terms_page_loads_with_expanded_sections(): void
    {
        $response = $this->get('/terms-and-conditions');

        $response->assertOk();
        $response->assertSee('Definitions', false);
        $response->assertSee('Developer program and partner attribution', false);
        $response->assertSee('Prohibited and restricted activities', false);
    }

    #[Test]
    public function privacy_page_loads_with_expanded_sections(): void
    {
        $response = $this->get('/privacy-policy');

        $response->assertOk();
        $response->assertSee('WhatsApp Wallet and messaging channels', false);
        $response->assertSee('Nigeria Data Protection Commission', false);
    }

    #[Test]
    public function security_and_fraud_pages_load(): void
    {
        $this->get('/security')->assertOk()->assertSee('Vulnerability reporting', false);
        $this->get('/fraud-awareness')->assertOk()->assertSee('What we will never ask you to do', false);
    }

    #[Test]
    public function legal_content_does_not_expose_trade_secrets(): void
    {
        $forbidden = ['MevonPay', 'email-matching', 'cron/process-emails', 'developer_program_partner_business_id'];

        foreach (['terms-and-conditions', 'privacy-policy', 'security', 'fraud-awareness'] as $slug) {
            $html = LegalPagesDefinitions::contentFor($slug);
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $html,
                    "Legal page [{$slug}] should not contain [{$needle}]"
                );
            }
        }
    }
}

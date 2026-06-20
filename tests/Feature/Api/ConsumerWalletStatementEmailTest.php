<?php

namespace Tests\Feature\Api;

use App\Mail\WalletStatementMail;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumerWalletStatementEmailTest extends TestCase
{
    use RefreshDatabase;

    private function actingWallet(array $overrides = []): WhatsappWallet
    {
        $wallet = WhatsappWallet::query()->create(array_merge([
            'phone_e164' => '2348012345678',
            'balance' => 10000,
            'pin_hash' => Hash::make('2468'),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'sender_name' => 'Statement User',
            'kyc_email' => 'statement.user@example.com',
        ], $overrides));

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        Sanctum::actingAs($account, ['consumer']);

        return $wallet;
    }

    public function test_statement_email_sends_csv_attachment(): void
    {
        Mail::fake();

        $wallet = $this->actingWallet();
        $tz = config('app.timezone', 'Africa/Lagos');
        $to = Carbon::now($tz)->format('Y-m-d');
        $from = Carbon::now($tz)->subMonths(6)->format('Y-m-d');

        WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_TOPUP,
            'amount' => 5000,
            'balance_after' => 15000,
            'ledger_scope' => 'personal',
            'meta' => ['label' => 'Bank top-up'],
            'created_at' => Carbon::now($tz)->subDays(3),
        ]);

        $response = $this->postJson('/api/v1/consumer/wallet/statement/email', [
            'format' => 'csv',
            'ledger_scope' => 'personal',
            'period' => '6mo',
            'from' => $from,
            'to' => $to,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Statement sent to your email.')
            ->assertJsonPath('data.format', 'csv')
            ->assertJsonPath('data.email_masked', 's***@example.com')
            ->assertJsonPath('data.period_label', 'Last 6 months');

        Mail::assertSent(WalletStatementMail::class, function (WalletStatementMail $mail): bool {
            return $mail->hasTo('statement.user@example.com')
                && $mail->format === 'csv'
                && str_ends_with($mail->fileName, '.csv')
                && str_contains($mail->fileContent, 'CheckoutNow Account Statement');
        });
    }

    public function test_statement_email_requires_profile_email(): void
    {
        Mail::fake();

        $this->actingWallet(['kyc_email' => null]);

        $response = $this->postJson('/api/v1/consumer/wallet/statement/email', [
            'format' => 'csv',
            'ledger_scope' => 'personal',
            'period' => '6mo',
            'from' => '2025-12-20',
            'to' => '2026-06-20',
        ]);

        $response->assertStatus(422);
        Mail::assertNothingSent();
    }
}

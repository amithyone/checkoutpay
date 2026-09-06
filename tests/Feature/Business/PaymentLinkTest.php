<?php

namespace Tests\Feature\Business;

use App\Listeners\MarkPaymentLinkPaidOnPaymentApproved;
use App\Events\PaymentApproved;
use App\Models\AccountNumber;
use App\Models\Admin;
use App\Models\Business;
use App\Models\Payment;
use App\Models\PaymentLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaymentLinkTest extends TestCase
{
    use RefreshDatabase;

    private function business(): Business
    {
        return Business::create([
            'name' => 'Link Shop',
            'email' => 'linkshop@test.com',
            'is_active' => true,
            'uses_external_account_numbers' => false,
        ]);
    }

    private function invoicePoolAccount(): AccountNumber
    {
        return AccountNumber::create([
            'account_number' => '5555555555',
            'account_name' => 'Invoice Pool',
            'bank_name' => 'Test Bank',
            'is_invoice_pool' => true,
            'is_active' => true,
        ]);
    }

    public function test_business_can_create_fixed_one_time_and_open_reusable_links(): void
    {
        $business = $this->business();
        $this->actingAs($business, 'business');

        $this->post('/dashboard/payment-links', [
            'title' => 'School fees',
            'description' => 'Term 1',
            'amount_mode' => 'fixed',
            'amount' => 25000,
            'reuse_mode' => 'one_time',
        ])->assertRedirect();

        $this->assertDatabaseHas('payment_links', [
            'business_id' => $business->id,
            'title' => 'School fees',
            'reuse_mode' => 'one_time',
            'status' => 'active',
        ]);
        $this->assertEquals(25000.00, (float) PaymentLink::where('title', 'School fees')->value('amount'));

        $this->post('/dashboard/payment-links', [
            'title' => 'Tip jar',
            'amount_mode' => 'open',
            'reuse_mode' => 'reusable',
        ])->assertRedirect();

        $open = PaymentLink::where('title', 'Tip jar')->first();
        $this->assertNotNull($open);
        $this->assertNull($open->amount);
        $this->assertSame('reusable', $open->reuse_mode);
    }

    public function test_public_pay_creates_payment_with_payment_link_service(): void
    {
        $this->invoicePoolAccount();
        $business = $this->business();
        $link = PaymentLink::create([
            'business_id' => $business->id,
            'title' => 'Deposit',
            'amount' => 1500,
            'currency' => 'NGN',
            'reuse_mode' => PaymentLink::REUSE_ONE_TIME,
            'status' => PaymentLink::STATUS_ACTIVE,
        ]);

        $this->get('/pay/l/'.$link->code)->assertOk()->assertSee('Deposit');

        $this->assertDatabaseHas('payments', [
            'business_id' => $business->id,
        ]);
        $payment = Payment::where('business_id', $business->id)->first();
        $this->assertSame('payment_link', $payment->email_data['service'] ?? null);
        $this->assertSame($link->id, (int) ($payment->email_data['payment_link_id'] ?? 0));
        $this->assertDatabaseHas('payment_link_payments', [
            'payment_link_id' => $link->id,
            'payment_id' => $payment->id,
        ]);
    }

    public function test_one_time_link_cannot_collect_after_approved(): void
    {
        $this->invoicePoolAccount();
        $business = $this->business();
        $link = PaymentLink::create([
            'business_id' => $business->id,
            'title' => 'Once only',
            'amount' => 800,
            'currency' => 'NGN',
            'reuse_mode' => PaymentLink::REUSE_ONE_TIME,
            'status' => PaymentLink::STATUS_ACTIVE,
        ]);

        $this->get('/pay/l/'.$link->code)->assertOk();
        $payment = Payment::where('business_id', $business->id)->first();
        $this->assertNotNull($payment);

        $payment->update(['status' => Payment::STATUS_APPROVED]);
        app(MarkPaymentLinkPaidOnPaymentApproved::class)->handle(new PaymentApproved($payment->fresh()));

        $link->refresh();
        $this->assertSame(PaymentLink::STATUS_PAID, $link->status);
        $this->assertSame(1, (int) $link->collected_count);

        $this->get('/pay/l/'.$link->code)
            ->assertOk()
            ->assertSee('This link has been paid');

        $this->assertSame(1, Payment::where('business_id', $business->id)->count());
    }

    public function test_admin_index_lists_links_across_businesses(): void
    {
        $a = $this->business();
        $b = Business::create([
            'name' => 'Other Biz',
            'email' => 'otherbiz@test.com',
            'is_active' => true,
        ]);
        PaymentLink::create([
            'business_id' => $a->id,
            'title' => 'Alpha link',
            'amount' => 100,
            'reuse_mode' => PaymentLink::REUSE_ONE_TIME,
        ]);
        PaymentLink::create([
            'business_id' => $b->id,
            'title' => 'Beta link',
            'amount' => null,
            'reuse_mode' => PaymentLink::REUSE_REUSABLE,
        ]);

        $admin = Admin::create([
            'name' => 'Boss',
            'email' => 'boss-links@test.com',
            'password' => Hash::make('secret'),
            'role' => Admin::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/enter0/payment-links')
            ->assertOk()
            ->assertSee('Alpha link')
            ->assertSee('Beta link')
            ->assertSee('Link Shop')
            ->assertSee('Other Biz');
    }
}

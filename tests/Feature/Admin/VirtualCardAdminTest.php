<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\VirtualCardRequest;
use App\Models\WhatsappWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VirtualCardAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    private function superAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Super',
            'email' => 'super-'.uniqid().'@check-outpay.com',
            'password' => Hash::make('password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_card_users(): void
    {
        $this->get(route('admin.virtual-cards.users'))
            ->assertRedirect();
    }

    public function test_admin_can_list_card_users_and_open_account(): void
    {
        $admin = $this->superAdmin();
        
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345678',
            'balance' => 5000,
            'kyc_fname' => 'Card',
            'kyc_lname' => 'User',
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        $card = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_ACTIVE,
            'fee_usd' => 7.5,
            'fee_ngn' => 7500,
            'card_name' => 'Card User',
            'card_external_id' => 'VCARD123456',
            'card_details_payload' => [
                'last_four' => '9876',
            ],
            'card_balance_usd' => 138.0,
            'activated_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.virtual-cards.users'))
            ->assertOk()
            ->assertSee('Card Users')
            ->assertSee('Card User')
            ->assertSee('+2348012345678')
            ->assertSee('9876')
            ->assertSee('$138.00');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.virtual-cards.show', $card))
            ->assertOk()
            ->assertSee('Customer / wallet')
            ->assertSee('Card transaction history (MevonPay)');
    }
}

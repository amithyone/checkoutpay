<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Business;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentImportTest extends TestCase
{
    use RefreshDatabase;

    private function actingSuperAdmin(): Admin
    {
        $admin = Admin::create([
            'name' => 'Import Admin',
            'email' => 'import-admin@example.com',
            'password' => Hash::make('secret'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_import_page_loads(): void
    {
        $this->actingSuperAdmin();

        $this->get(route('admin.payments.import'))
            ->assertOk()
            ->assertSee('Import payments');
    }

    public function test_upload_import_creates_rows(): void
    {
        Storage::fake('local');
        $this->actingSuperAdmin();

        $business = Business::create([
            'name' => 'Upload Biz',
            'email' => 'upload-biz-'.uniqid().'@test.com',
            'api_key' => 'pk_up_'.uniqid(),
            'is_active' => true,
            'balance' => 0,
        ]);

        $csv = "transaction_id,amount,status,payer_name\nUP-1,500,pending,Chioma\n";
        $file = UploadedFile::fake()->createWithContent('batch.csv', $csv);

        $this->post(route('admin.payments.import.store'), [
            'business_id' => $business->id,
            'source' => 'upload',
            'csv_file' => $file,
        ])->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'transaction_id' => 'UP-1',
            'business_id' => $business->id,
            'status' => Payment::STATUS_PENDING,
        ]);
    }
}

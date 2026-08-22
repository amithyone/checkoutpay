<?php

namespace Tests\Unit\WalletImport;

use App\Services\WalletImport\FormCsvBankResolver;
use App\Services\WalletImport\FormCsvWalletSeedService;
use App\Services\WalletImport\FormCsvWalletSterilizerService;
use App\Services\WhatsappWalletBankPayoutService;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletPayCodeService;
use App\Services\MevonPay\PrivateAccountProvisionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class FormCsvWalletImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Config::set('wallet_import_banks.aliases', [
            'uba' => '033',
            'gt' => '058',
            'gtb' => '058',
            'access' => '044',
            'first' => '011',
            'firstbank' => '011',
            'zenith' => '057',
        ]);
        Config::set('wallet_import_banks.name_enquiry_delay_ms', 0);
    }

    public function test_bank_alias_resolver(): void
    {
        $resolver = new FormCsvBankResolver;
        $uba = $resolver->resolve('UBA Bank');
        $this->assertNotNull($uba);
        $this->assertSame('alias', $uba['resolved_via']);
        $this->assertSame('000004', $uba['bank_code']); // legacy 033 → NIP

        $gtb = $resolver->resolve('GTBank');
        $this->assertNotNull($gtb);
        $this->assertSame('000013', $gtb['bank_code']);

        $this->assertSame('alias', $resolver->resolve('Access')['resolved_via']);
        $this->assertSame('alias', $resolver->resolve('First bank')['resolved_via']);
        $this->assertNull($resolver->resolve('Totally Unknown Microfinance XYZ'));
    }

    public function test_split_person_name(): void
    {
        $bankPayout = Mockery::mock(WhatsappWalletBankPayoutService::class);
        $svc = new FormCsvWalletSterilizerService(new FormCsvBankResolver, $bankPayout);

        $this->assertSame(['Ada', 'Okafor Musa'], $svc->splitPersonName('Ada Okafor Musa'));
        $this->assertSame(['Chinonso', ''], $svc->splitPersonName('Chinonso'));
    }

    public function test_parse_dob_and_gender(): void
    {
        $bankPayout = Mockery::mock(WhatsappWalletBankPayoutService::class);
        $svc = new FormCsvWalletSterilizerService(new FormCsvBankResolver, $bankPayout);

        $this->assertSame('1990-05-12', $svc->parseDob('12/05/1990'));
        $this->assertSame('male', $svc->normalizeGender('Male'));
        $this->assertSame('female', $svc->normalizeGender('F'));
    }

    public function test_sterilize_row_uses_name_enquiry(): void
    {
        $bankPayout = Mockery::mock(WhatsappWalletBankPayoutService::class);
        $bankPayout->shouldReceive('isNameEnquiryAvailable')->andReturn(true);
        $bankPayout->shouldReceive('nameEnquiryPrimary')->once()->andReturn([
            'account_name' => 'RUFAI ITOPA YUSUF',
            'bank_code' => '033',
        ]);
        $bankPayout->shouldReceive('isWeakVerifiedName')->andReturn(false);

        $svc = new FormCsvWalletSterilizerService(new FormCsvBankResolver, $bankPayout);
        $header = [
            'Timestamp', 'Name', 'Surname', 'Gender', 'Phone Number', 'Address( LGA & State)',
            'Project', 'Next of Kin Full Name', 'Next of Kin Address', 'Next of Kin Phone No.',
            'Email Address', 'Account Number', 'Bank', 'Account Type', 'Date Of Birth', 'BVN',
            'Upload', 'Account Number', 'Cleaned First Name', 'Cleaned Surname',
        ];
        $indexes = $svc->columnIndexes($header);
        $raw = array_fill(0, 20, '');
        $raw[1] = 'Messy';
        $raw[2] = 'Name';
        $raw[3] = 'Male';
        $raw[4] = '08135426996';
        $raw[10] = 'test@example.com';
        $raw[11] = '1234567890';
        $raw[12] = 'UBA';
        $raw[14] = '12/05/1990';
        $raw[15] = '22123456789';
        $raw[17] = '0123456789';
        $raw[18] = 'Messy';
        $raw[19] = 'Name';

        $row = $svc->sterilizeRow($raw, $indexes, 2, false);

        $this->assertSame('ok', $row['status']);
        $this->assertSame('2348135426996', $row['phone_e164']);
        $this->assertSame('name_enquiry', $row['name_source']);
        $this->assertSame('RUFAI', $row['kyc_fname']);
        $this->assertSame('ITOPA YUSUF', $row['kyc_lname']);
        $this->assertSame(2, $row['tier_target']);
        $this->assertSame('0123456789', $row['account_number']);
    }

    public function test_seed_skips_existing_phone(): void
    {
        $schema = Schema::connection('sqlite');
        $schema->create('whatsapp_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('phone_e164')->unique();
            $table->string('pay_code')->nullable();
            $table->unsignedTinyInteger('tier')->default(1);
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('status', 32)->default('active');
            $table->string('kyc_fname')->nullable();
            $table->string('kyc_lname')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('kyc_gender')->nullable();
            $table->date('kyc_dob')->nullable();
            $table->string('kyc_email')->nullable();
            $table->string('kyc_bvn')->nullable();
            $table->string('card_home_address')->nullable();
            $table->timestamps();
        });

        WhatsappWallet::query()->create([
            'phone_e164' => '2348011111111',
            'tier' => 1,
            'status' => 'active',
            'balance' => 0,
        ]);

        $path = sys_get_temp_dir().'/form-seed-test-'.uniqid().'.jsonl';
        file_put_contents($path, json_encode([
            'status' => 'ok',
            'phone_e164' => '2348011111111',
            'kyc_fname' => 'Ada',
            'kyc_lname' => 'Okafor',
            'confirmed_account_name' => 'Ada Okafor',
            'tier_target' => 1,
        ])."\n".json_encode([
            'status' => 'ok',
            'phone_e164' => '2348022222222',
            'kyc_fname' => 'Bola',
            'kyc_lname' => 'Ade',
            'confirmed_account_name' => 'Bola Ade',
            'tier_target' => 1,
        ])."\n");

        $seeder = new FormCsvWalletSeedService(
            app(ConsumerWalletPayCodeService::class),
            app(PrivateAccountProvisionService::class),
        );
        $stats = $seeder->seedFromJsonl($path, true, true, false);

        $this->assertSame(1, $stats['skipped_existing']);
        $this->assertSame(1, $stats['created']);
        $this->assertSame(1, $stats['would_create']);
        $this->assertTrue(WhatsappWallet::query()->where('phone_e164', '2348022222222')->exists());

        @unlink($path);
    }
}

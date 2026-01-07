<?php

namespace Database\Seeders;

use App\Models\AccountNumber;
use Illuminate\Database\Seeder;

class AccountNumberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create some pool account numbers
        AccountNumber::create([
            'account_number' => '1234567890',
            'account_name' => 'Payment Gateway Pool 1',
            'bank_name' => 'GTB',
            'is_pool' => true,
            'is_active' => true,
        ]);

        AccountNumber::create([
            'account_number' => '0987654321',
            'account_name' => 'Payment Gateway Pool 2',
            'bank_name' => 'Access Bank',
            'is_pool' => true,
            'is_active' => true,
        ]);
    }
}

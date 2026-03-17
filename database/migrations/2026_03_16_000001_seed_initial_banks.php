<?php

use App\Models\Bank;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $banks = [
            ['code' => '044', 'name' => 'Access Bank'],
            ['code' => '063', 'name' => 'Access Bank (Diamond)'],
            ['code' => '050', 'name' => 'Ecobank Nigeria'],
            ['code' => '058', 'name' => 'GTBank'],
            ['code' => '011', 'name' => 'First Bank of Nigeria'],
            ['code' => '214', 'name' => 'First City Monument Bank'],
            ['code' => '082', 'name' => 'Keystone Bank'],
            ['code' => '221', 'name' => 'Stanbic IBTC Bank'],
            ['code' => '068', 'name' => 'Standard Chartered Bank'],
            ['code' => '232', 'name' => 'Sterling Bank'],
            ['code' => '032', 'name' => 'Union Bank of Nigeria'],
            ['code' => '033', 'name' => 'United Bank For Africa'],
            ['code' => '215', 'name' => 'Unity Bank'],
            ['code' => '035', 'name' => 'Wema Bank'],
            ['code' => '057', 'name' => 'Zenith Bank'],
        ];

        foreach ($banks as $bank) {
            Bank::updateOrCreate(
                ['code' => $bank['code']],
                ['name' => $bank['name']],
            );
        }
    }

    public function down(): void
    {
        // Do not delete banks on rollback to avoid losing data
    }
};


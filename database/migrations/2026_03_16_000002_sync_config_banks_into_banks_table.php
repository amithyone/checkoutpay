<?php

use App\Models\Bank;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Copy all banks from config/banks.php into the banks table.
     */
    public function up(): void
    {
        $configBanks = config('banks', []);

        if (! is_array($configBanks)) {
            return;
        }

        foreach ($configBanks as $bank) {
            $code = $bank['code'] ?? null;
            $name = $bank['bank_name'] ?? $bank['name'] ?? null;

            if (! $code || ! $name) {
                continue;
            }

            Bank::updateOrCreate(
                ['code' => $code],
                ['name' => $name],
            );
        }
    }

    public function down(): void
    {
        // Do not delete banks on rollback to avoid losing production data.
    }
};


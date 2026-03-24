<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$businessId = \App\Models\Setting::get('wallet_funding_business_id', null);
$business = $businessId ? \App\Models\Business::find($businessId) : \App\Models\Business::whereNotNull('id')->first();

if ($business) {
    \App\Models\AccountNumber::updateOrCreate(
        ['business_id' => $business->id, 'bank_name' => 'Guaranty Trust Bank'],
        [
            'account_number' => '0581234567',
            'account_name' => 'Rentals Corporate Account',
            'is_pool' => false,
            'is_active' => true,
        ]
    );
    echo "Account seeded for business ID {$business->id}\n";
} else {
    echo "No business found to seed account for.\n";
}

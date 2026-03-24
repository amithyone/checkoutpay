<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$businessId = \App\Models\Setting::get('wallet_funding_business_id', null);
$business = $businessId ? \App\Models\Business::find($businessId) : \App\Models\Business::whereNotNull('id')->first();
$accountNumber = $business ? \App\Models\AccountNumber::where('business_id', $business->id)->active()->first() : null;

print_r([
    'businessId' => $businessId,
    'business' => $business ? $business->id : null,
    'accountNumber' => $accountNumber ? $accountNumber->toArray() : null,
    'all_accounts_for_business' => $business ? \App\Models\AccountNumber::where('business_id', $business->id)->get()->toArray() : [],
]);

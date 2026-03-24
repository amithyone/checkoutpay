<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$acct = \App\Models\AccountNumber::where('account_number', '0581234567')->first();
if ($acct) {
    if (in_array(Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($acct))) {
        $acct->forceDelete();
    } else {
        $acct->delete();
    }
    echo "Deleted seeded account.\n";
} else {
    echo "No seeded account found.\n";
}

app(\App\Services\AccountNumberService::class)->invalidateAllCaches();
echo "Cache cleared.\n";

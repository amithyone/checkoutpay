<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerBusinessActivityService;
use App\Services\Consumer\ConsumerBusinessWalletLedgerService;

$wallet = WhatsappWallet::query()
    ->where(function ($q) {
        $q->whereNotNull('linked_business_id')->orWhere('business_balance', '>', 0);
    })
    ->first();

if (! $wallet) {
    echo "No business wallet found\n";
    exit(1);
}

$ledger = app(ConsumerBusinessWalletLedgerService::class);
$business = $ledger->resolveLinkedOrMatchedBusiness($wallet);
echo "wallet={$wallet->id} business=".($business?->id ?? 'null')."\n";

if (! $business) {
    exit(0);
}

try {
    $result = app(ConsumerBusinessActivityService::class)->paginate(
        $wallet,
        $business,
        '2026-05-19',
        '2026-06-17',
        1,
        50,
    );
    echo 'total='.$result['total'].' items='.count($result['items'])."\n";
    $json = json_encode($result['items'], JSON_THROW_ON_ERROR);
    echo 'json_len='.strlen($json)."\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
    echo $e->getTraceAsString()."\n";
    exit(1);
}

<?php

namespace App\Providers;

use App\Services\PartnerBanks\PartnerBankRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Wires encoded partner bank adapters (shipped per release) to the readable slug registry.
 */
class PartnerBankServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PartnerBankRegistry::class);
    }

    public function boot(): void
    {
        //
    }
}

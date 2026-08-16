<?php

namespace App\Observers;

use App\Models\Business;
use App\Services\BusinessMevonExternalDefaultService;

class BusinessObserver
{
    public function created(Business $business): void
    {
        if (app()->environment('testing')) {
            return;
        }

        app(BusinessMevonExternalDefaultService::class)->ensureAssigned($business);
    }
}

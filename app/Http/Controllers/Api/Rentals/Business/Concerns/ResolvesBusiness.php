<?php

namespace App\Http\Controllers\Api\Rentals\Business\Concerns;

use App\Models\Business;
use App\Models\Renter;
use App\Services\Rentals\RenterPortalAccountBridge;
use Illuminate\Http\Request;

trait ResolvesBusiness
{
    protected function resolveBusinessOr403(Request $request): Business
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $business = RenterPortalAccountBridge::businessLinkedToRenterEmail($renter->email);
        if (! $business) {
            abort(response()->json(['message' => 'Business access required.'], 403));
        }

        return $business;
    }
}


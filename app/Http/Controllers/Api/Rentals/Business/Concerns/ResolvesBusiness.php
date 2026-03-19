<?php

namespace App\Http\Controllers\Api\Rentals\Business\Concerns;

use App\Models\Business;
use App\Models\Renter;
use Illuminate\Http\Request;

trait ResolvesBusiness
{
    protected function resolveBusinessOr403(Request $request): Business
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $business = Business::where('email', $renter->email)->first();
        if (! $business) {
            abort(response()->json(['message' => 'Business access required.'], 403));
        }

        return $business;
    }
}


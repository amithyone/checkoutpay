<?php

namespace App\Http\Controllers\Api\Rentals;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\RentalDeviceToken;
use App\Models\Renter;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * POST /api/v1/rentals/devices/register
     * Register or refresh a device token for renter/business notifications.
     */
    public function register(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $validated = $request->validate([
            'token' => 'required|string|max:2048',
            'platform' => 'required|string|in:android,ios,web',
            'device_name' => 'nullable|string|max:255',
        ]);

        $businessId = Business::query()
            ->whereRaw('LOWER(email) = LOWER(?)', [$renter->email])
            ->value('id');

        $token = RentalDeviceToken::query()->updateOrCreate(
            ['token' => $validated['token']],
            [
                'renter_id' => $renter->id,
                'business_id' => $businessId,
                'platform' => $validated['platform'],
                'device_name' => $validated['device_name'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $token->id,
                'platform' => $token->platform,
                'last_seen_at' => optional($token->last_seen_at)->toIso8601String(),
            ],
        ]);
    }
}


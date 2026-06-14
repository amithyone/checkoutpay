<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Consumer\ConsumerVirtualCardService;
use App\Support\MarketingVirtualCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VirtualCardFxController extends Controller
{
    public function rates(Request $request): JsonResponse
    {
        if (! app(ConsumerVirtualCardService::class)->isEnabled()) {
            return response()->json(['ok' => false, 'enabled' => false], 404);
        }

        $fetchFresh = filter_var($request->query('fresh', '1'), FILTER_VALIDATE_BOOLEAN);

        return response()->json(
            MarketingVirtualCard::appRates($fetchFresh)
        );
    }
}

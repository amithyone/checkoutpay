<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Admin\MevonPayFxRateTrackerService;
use App\Services\Consumer\ConsumerVirtualCardService;
use App\Services\Consumer\VirtualCardFxPublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VirtualCardFxController extends Controller
{
    public function rates(Request $request): JsonResponse
    {
        if (! app(ConsumerVirtualCardService::class)->isEnabled()) {
            return response()->json(['ok' => false, 'enabled' => false], 404);
        }

        $published = app(VirtualCardFxPublishService::class)->publishedSnapshot();
        if ($published['sell_rate'] === null || $published['buy_rate'] === null) {
            app(VirtualCardFxPublishService::class)->syncFromMevon();
        }

        $fetchFresh = filter_var($request->query('fresh', '1'), FILTER_VALIDATE_BOOLEAN);

        return response()->json(
            app(MevonPayFxRateTrackerService::class)->calculatorRates($fetchFresh)
        );
    }
}

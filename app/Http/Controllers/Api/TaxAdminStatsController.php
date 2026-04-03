<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NigtaxRevenueStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TaxAdminStatsController extends Controller
{
    public function index(): JsonResponse
    {
        $today = now()->startOfDay();

        $pageViewsTotal = DB::table('nigtax_site_visits')->count();
        $pageViewsToday = DB::table('nigtax_site_visits')->where('created_at', '>=', $today)->count();

        $businessCount = DB::table('nigtax_business_records')->count();
        $personalCount = DB::table('nigtax_personal_records')->count();

        $last7 = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i)->startOfDay();
            $end = $d->copy()->addDay();
            $last7[] = [
                'date' => $d->toDateString(),
                'label' => $d->format('D j M'),
                'visits' => DB::table('nigtax_site_visits')
                    ->where('created_at', '>=', $d)
                    ->where('created_at', '<', $end)
                    ->count(),
            ];
        }

        $revenue = app(NigtaxRevenueStatsService::class);

        return response()->json([
            'page_views_total' => $pageViewsTotal,
            'page_views_today' => $pageViewsToday,
            'business_submissions' => $businessCount,
            'personal_submissions' => $personalCount,
            'submissions_total' => $businessCount + $personalCount,
            'visits_last_7_days' => $last7,
            'nigtax_pro' => $revenue->proPlanStats($today),
            'nigtax_certified' => $revenue->certifiedRevenueStats($today),
        ]);
    }
}

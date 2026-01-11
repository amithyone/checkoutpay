<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Show activity logs
     */
    public function index(Request $request)
    {
        $business = auth('business')->user();
        
        $query = $business->activityLogs()->latest();

        // Filter by action
        if ($request->has('action') && $request->action) {
            $query->where('action', $request->action);
        }

        // Filter by date range
        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $logs = $query->paginate(50);

        return view('business.activity.index', compact('logs'));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiHitLog;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiHitLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = ApiHitLog::query()->with('business:id,name,email')->latest('id');

        if ($request->filled('result')) {
            if ($request->input('result') === 'success') {
                $query->where('successful', true);
            } elseif ($request->input('result') === 'failed') {
                $query->where('successful', false);
            }
        }

        if ($request->filled('path')) {
            $query->where('path', 'like', '%'.$request->input('path').'%');
        }

        if ($request->filled('website')) {
            $query->where('website_host', 'like', '%'.$request->input('website').'%');
        }

        if ($request->filled('business_id')) {
            $query->where('business_id', (int) $request->input('business_id'));
        }

        if ($request->filled('status_code')) {
            $query->where('status_code', (int) $request->input('status_code'));
        }

        $logs = $query->paginate(50)->withQueryString();

        $stats = [
            'success' => ApiHitLog::query()->where('successful', true)->count(),
            'failed' => ApiHitLog::query()->where('successful', false)->count(),
        ];

        $businesses = Business::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.api-hits.index', compact('logs', 'stats', 'businesses'));
    }

    public function show(ApiHitLog $apiHit): View
    {
        $apiHit->load('business:id,name,email');

        return view('admin.api-hits.show', compact('apiHit'));
    }
}

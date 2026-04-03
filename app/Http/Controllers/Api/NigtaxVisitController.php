<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NigtaxVisitController extends Controller
{
    /**
     * Record a public calculator page view (called once per browser session from NigTax frontend).
     */
    public function store(Request $request): JsonResponse
    {
        $path = $request->input('path');
        if (is_string($path) && strlen($path) > 500) {
            $path = substr($path, 0, 500);
        }

        DB::table('nigtax_site_visits')->insert([
            'path' => $path,
            'created_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}

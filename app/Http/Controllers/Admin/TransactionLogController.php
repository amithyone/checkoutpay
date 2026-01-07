<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransactionLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionLogController extends Controller
{
    /**
     * Display transaction logs
     */
    public function index(Request $request): View
    {
        $query = TransactionLog::with(['payment', 'business'])->latest();

        // Filter by transaction ID
        if ($request->has('transaction_id')) {
            $query->where('transaction_id', $request->transaction_id);
        }

        // Filter by event type
        if ($request->has('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        // Filter by business
        if ($request->has('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $logs = $query->paginate(50);

        return view('admin.transaction-logs.index', compact('logs'));
    }

    /**
     * Show logs for a specific transaction
     */
    public function show(string $transactionId): View
    {
        $logs = TransactionLog::forTransaction($transactionId)
            ->with(['payment', 'business'])
            ->get();

        return view('admin.transaction-logs.show', compact('logs', 'transactionId'));
    }
}

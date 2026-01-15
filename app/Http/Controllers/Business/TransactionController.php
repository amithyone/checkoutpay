<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $business = Auth::guard('business')->user();

        $query = $business->payments()->with('website')->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search by transaction ID
        if ($request->filled('search')) {
            $query->where('transaction_id', 'like', '%' . $request->search . '%');
        }

        $transactions = $query->paginate(20);

        return view('business.transactions.index', compact('transactions'));
    }

    public function show($id)
    {
        $business = Auth::guard('business')->user();
        $transaction = $business->payments()->with('website')->findOrFail($id);

        return view('business.transactions.show', compact('transaction'));
    }
}

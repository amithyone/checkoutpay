<?php

namespace App\Http\Controllers\Api\Rentals\Business;

use App\Http\Controllers\Api\Rentals\Business\Concerns\ResolvesBusiness;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WithdrawalAccountsController extends Controller
{
    use ResolvesBusiness;

    /**
     * GET /api/v1/rentals/business/withdrawal-accounts
     */
    public function index(Request $request)
    {
        $business = $this->resolveBusinessOr403($request);

        $accounts = $business->withdrawalAccounts()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'account_number',
                'account_name',
                'bank_name',
                'bank_code',
                'is_default',
            ]);

        return response()->json([
            'data' => $accounts,
        ]);
    }

    /**
     * POST /api/v1/rentals/business/withdrawal-accounts
     * Create or update a saved withdrawal account for this business.
     */
    public function store(Request $request)
    {
        $business = $this->resolveBusinessOr403($request);

        $validated = $request->validate([
            'account_number' => 'required|string|size:10',
            'account_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'bank_code' => 'required|string|max:20',
            'is_default' => 'boolean',
        ]);

        $existing = $business->withdrawalAccounts()
            ->where('account_number', $validated['account_number'])
            ->where('bank_code', $validated['bank_code'])
            ->first();

        if ($existing) {
            $existing->update([
                'account_name' => $validated['account_name'],
                'bank_name' => $validated['bank_name'],
                'is_active' => true,
            ]);

            if ($validated['is_default'] ?? false) {
                $existing->setAsDefault();
            }

            $account = $existing->fresh();
        } else {
            $account = $business->withdrawalAccounts()->create([
                'account_number' => $validated['account_number'],
                'account_name' => $validated['account_name'],
                'bank_name' => $validated['bank_name'],
                'bank_code' => $validated['bank_code'],
                'is_default' => $validated['is_default'] ?? false,
                'is_active' => true,
            ]);

            if ($account->is_default) {
                $account->setAsDefault();
            }
        }

        return response()->json([
            'data' => $account,
        ], 201);
    }
}


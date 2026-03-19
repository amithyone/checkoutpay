<?php

namespace App\Http\Controllers\Api\Rentals\Business;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Rentals\Business\Concerns\ResolvesBusiness;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    use ResolvesBusiness;

    /**
     * GET /api/v1/rentals/business/summary
     */
    public function __invoke(Request $request)
    {
        $business = $this->resolveBusinessOr403($request);

        $pendingOrders = Rental::where('business_id', $business->id)
            ->where('status', Rental::STATUS_PENDING)
            ->count();

        // Approved = paid but not yet picked up (pickup requests)
        $approved = Rental::where('business_id', $business->id)
            ->where('status', Rental::STATUS_APPROVED)
            ->count();

        $active = Rental::where('business_id', $business->id)
            ->where('status', Rental::STATUS_ACTIVE)
            ->count();

        $inventoryCount = RentalItem::where('business_id', $business->id)->count();
        $inventoryQty = (int) (RentalItem::where('business_id', $business->id)->sum('quantity_available') ?? 0);

        $pendingWithdrawals = WithdrawalRequest::where('business_id', $business->id)
            ->where('status', WithdrawalRequest::STATUS_PENDING)
            ->count();

        $dueForReturn = Rental::where('business_id', $business->id)
            ->where('status', Rental::STATUS_ACTIVE)
            ->whereNull('returned_at')
            ->whereDate('end_date', '<=', now()->toDateString())
            ->count();

        // Earnings today:
        // - For bank-transfer approvals: matched_at is set, business_receives is populated.
        // - For wallet rentals: we record a Payment row but matched_at/business_receives may be null.
        // We therefore:
        // - filter to rental payments (rental_id not null)
        // - treat "today" as matched_at when present, otherwise created_at
        // - sum business_receives when present, otherwise amount
        $earningsToday = (float) (Payment::query()
            ->where('business_id', $business->id)
            ->whereNotNull('rental_id')
            ->where(function ($q) {
                $today = now()->toDateString();
                $q->whereDate('matched_at', $today)
                    ->orWhere(function ($q2) use ($today) {
                        $q2->whereNull('matched_at')->whereDate('created_at', $today);
                    });
            })
            ->whereNotIn('status', [Payment::STATUS_REJECTED, Payment::STATUS_PENDING])
            ->selectRaw('COALESCE(SUM(COALESCE(business_receives, amount)), 0) as total')
            ->value('total') ?? 0);

        return response()->json([
            'business' => [
                'id' => $business->id,
                'business_id' => $business->business_id ?? null,
                'name' => $business->name ?? null,
                'address' => $business->address ?? null,
            ],
            'counts' => [
                'pending_orders' => $pendingOrders,
                'pickup_requests' => $approved,
                'rentals_out' => $active,
                'due_for_return' => $dueForReturn,
                'inventory_count' => $inventoryCount,
                'inventory_qty' => $inventoryQty,
                'pending_withdrawals' => $pendingWithdrawals,
            ],
            'balance' => (float) ($business->balance ?? 0),
            'earnings_today' => $earningsToday,
        ]);
    }
}


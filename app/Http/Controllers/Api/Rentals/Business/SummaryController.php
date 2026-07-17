<?php

namespace App\Http\Controllers\Api\Rentals\Business;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Rentals\Business\Concerns\ResolvesBusiness;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\WithdrawalRequest;
use App\Services\Region\RegionCapabilitiesService;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    use ResolvesBusiness;

    /**
     * GET /api/v1/rentals/business/summary
     */
    public function __invoke(Request $request, RegionCapabilitiesService $regions)
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

        $region = $this->regionForBusiness($business, $regions);
        $features = is_array($region['features'] ?? null) ? $region['features'] : [];
        $bankPayinVa = (bool) ($features['bank_payin_va'] ?? false);

        return response()->json([
            'business' => [
                'id' => $business->id,
                'business_id' => $business->business_id ?? null,
                'name' => $business->name ?? null,
                'address' => $business->address ?? null,
                'phone' => $business->phone ?? null,
                'currency' => $region['currency'] ?? ($business->currency ?? 'NGN'),
                'country' => $region['country'] ?? null,
            ],
            'region' => $region,
            'capabilities' => [
                'bank_payin_va' => $bankPayinVa,
                'nip_transfer' => $bankPayinVa,
                'bank_payout' => (bool) ($features['bank_payout'] ?? false),
                'mpesa_payout' => (bool) ($features['mpesa_payout'] ?? false),
                'mpesa_collection' => (bool) ($features['mpesa_collection'] ?? false),
                'bills' => (bool) ($features['bills'] ?? false),
                'airtime' => (bool) ($features['airtime'] ?? false),
                'cross_border_p2p' => (bool) ($features['cross_border_p2p'] ?? false),
                'rails' => $region['rails'] ?? null,
                'messaging' => $bankPayinVa
                    ? null
                    : 'Kenya businesses use Cashwyre rails for payouts/mobile money. Nigeria-style virtual account / NIP collection is not available.',
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

    /**
     * @param  \App\Models\Business  $business
     * @return array<string, mixed>
     */
    protected function regionForBusiness($business, RegionCapabilitiesService $regions): array
    {
        $phone = trim((string) ($business->phone ?? ''));
        if ($phone !== '') {
            return $regions->forPhone($phone);
        }

        $currency = strtoupper(trim((string) ($business->currency ?? '')));
        if ($currency === 'KES') {
            return $regions->forCountryIso('KE');
        }

        return $regions->forCountryIso('NG');
    }
}

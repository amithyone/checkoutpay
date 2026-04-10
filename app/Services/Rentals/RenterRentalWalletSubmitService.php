<?php

namespace App\Services\Rentals;

use App\Mail\RentalReceipt;
use App\Mail\RentalRequestReceived;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Renter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RenterRentalWalletSubmitService
{
    /**
     * Create one rental paid from wallet (same rules as API checkout wallet branch).
     *
     * @param  array<int, string>  $selectedDates  Y-m-d strings, consecutive, unique
     * @return array{ok: bool, message: string, rental_id?: int}
     */
    public function submit(Renter $renter, int $itemId, int $quantity, array $selectedDates): array
    {
        $renter->refresh();

        if (! $renter->hasVerifiedEmail()) {
            return ['ok' => false, 'message' => 'Verify your email on the website before renting.'];
        }

        if (! $renter->isKycVerified()) {
            $rentalsUrl = rtrim((string) config('whatsapp.portals.rentals', 'https://abjrentals.ng'), '/');

            return ['ok' => false, 'message' => "Complete rentals KYC at {$rentalsUrl} before paying."];
        }

        if ($quantity < 1) {
            return ['ok' => false, 'message' => 'Quantity must be at least 1.'];
        }

        $item = RentalItem::query()
            ->where('id', $itemId)
            ->where('is_active', true)
            ->where('is_available', true)
            ->with(['business', 'category'])
            ->first();

        if (! $item || ! $item->business) {
            return ['ok' => false, 'message' => 'That item is not available.'];
        }

        $selectedDates = array_values(array_unique(array_map(function ($d) {
            return Carbon::parse($d)->format('Y-m-d');
        }, $selectedDates)));

        sort($selectedDates);

        if ($selectedDates === []) {
            return ['ok' => false, 'message' => 'Pick at least one rental day.'];
        }

        $today = now()->startOfDay();
        foreach ($selectedDates as $d) {
            if (Carbon::parse($d)->lt($today)) {
                return ['ok' => false, 'message' => 'Dates cannot be in the past.'];
            }
        }

        $n = count($selectedDates);
        for ($i = 1; $i < $n; $i++) {
            $prev = Carbon::parse($selectedDates[$i - 1])->startOfDay();
            $cur = Carbon::parse($selectedDates[$i])->startOfDay();
            if ($prev->copy()->addDay()->notEqualTo($cur)) {
                return ['ok' => false, 'message' => 'Rental days must be consecutive (e.g. Mon Tue Wed).'];
            }
        }

        $days = $n;
        $startDate = Carbon::parse($selectedDates[0])->startOfDay();
        $endDate = Carbon::parse($selectedDates[$n - 1])->startOfDay();

        if (! $item->isAvailableForDates($startDate->format('Y-m-d'), $endDate->format('Y-m-d'))) {
            return ['ok' => false, 'message' => 'Those dates are not available for this item. Try other dates.'];
        }

        $rate = $item->getRateForPeriod($days);
        $itemTotal = $rate * $quantity;
        $globalEnabled = (bool) ($item->business?->rental_global_caution_fee_enabled ?? false);
        $globalPercent = (float) ($item->business?->rental_global_caution_fee_percent ?? 0);
        $cautionPercent = $globalEnabled
            ? $globalPercent
            : ($item->caution_fee_enabled ? (float) $item->caution_fee_percent : 0.0);
        $itemCaution = $cautionPercent > 0 ? round(($itemTotal * $cautionPercent) / 100, 2) : 0.0;

        $grandTotal = $itemTotal + $itemCaution;

        if ((float) ($renter->wallet_balance ?? 0.0) < $grandTotal) {
            return [
                'ok' => false,
                'message' => 'Insufficient wallet balance. You need ₦'.number_format($grandTotal, 2)
                    .' (rent ₦'.number_format($itemTotal, 2).' + caution ₦'.number_format($itemCaution, 2)
                    .'). Reply FUND to add money.',
            ];
        }

        $payerName = $renter->verified_account_name ?: $renter->name;

        try {
            $rentalId = DB::transaction(function () use ($renter, $item, $quantity, $startDate, $endDate, $days, $itemTotal, $itemCaution, $rate, $payerName) {
                $business = $item->business;

                $rental = Rental::create([
                    'renter_id' => $renter->id,
                    'business_id' => $business->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'days' => $days,
                    'daily_rate' => $itemTotal / $days,
                    'total_amount' => $itemTotal,
                    'deposit_amount' => $itemCaution,
                    'currency' => 'NGN',
                    'status' => Rental::STATUS_PENDING,
                    'verified_account_number' => $renter->verified_account_number,
                    'verified_account_name' => $renter->verified_account_name,
                    'verified_bank_name' => $renter->verified_bank_name,
                    'verified_bank_code' => $renter->verified_bank_code,
                    'renter_name' => $payerName,
                    'renter_email' => $renter->email,
                    'renter_phone' => $renter->phone,
                    'renter_address' => $renter->address,
                    'business_phone' => $business->phone,
                    'renter_notes' => '[WhatsApp booking]',
                ]);

                $rental->items()->attach($item->id, [
                    'quantity' => $quantity,
                    'unit_rate' => $rate,
                    'total_amount' => $itemTotal,
                ]);

                $payable = $itemTotal + $itemCaution;
                $renter->wallet_balance = (float) ($renter->wallet_balance ?? 0.0) - $payable;
                $renter->save();

                try {
                    app(\App\Services\PushNotificationService::class)->notifyRenter(
                        (int) $renter->id,
                        'Wallet debited',
                        'Your wallet was debited for a rental payment.',
                        [
                            'type' => 'wallet_debit',
                            'amount' => (string) $payable,
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::warning('Push notify failed for wallet debit', [
                        'renter_id' => $renter->id,
                        'amount' => $payable,
                        'error' => $e->getMessage(),
                    ]);
                }

                $payment = Payment::create([
                    'transaction_id' => 'WLT-'.strtoupper(uniqid()),
                    'amount' => $payable,
                    'payer_name' => $payerName,
                    'business_id' => $business->id,
                    'renter_id' => $renter->id,
                    'status' => Payment::STATUS_APPROVED,
                    'service' => 'rental',
                    'rental_id' => $rental->id,
                ]);

                $rental->update([
                    'payment_id' => $payment->id,
                    'status' => Rental::STATUS_APPROVED,
                ]);

                Mail::to($business->email)->send(new RentalRequestReceived($rental->fresh()));
                Mail::to($renter->email)->send(new RentalReceipt($rental->fresh()));

                return $rental->id;
            });

            return [
                'ok' => true,
                'message' => 'Booking confirmed and paid from your wallet. Check your email for details.',
                'rental_id' => $rentalId,
            ];
        } catch (\Throwable $e) {
            Log::error('WhatsApp rental wallet submit failed', [
                'renter_id' => $renter->id,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Could not complete booking. Try again or use the website.'];
        }
    }
}

<?php

namespace App\Services\Rentals;

use App\Models\Business;
use App\Models\Payment;
use App\Models\Renter;
use App\Models\Setting;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class RenterWalletFundPaymentService
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Create a pending bank-transfer payment that credits the renter wallet when approved.
     *
     * @return array{ok: bool, payment?: array<string, mixed>, message?: string}
     */
    public function createFundPayment(Renter $renter, float $amount, ?Request $request = null): array
    {
        if ($amount < 1) {
            return ['ok' => false, 'message' => 'Minimum amount is ₦1.'];
        }

        $businessId = Setting::get('wallet_funding_business_id', null);
        $business = $businessId ? Business::find($businessId) : Business::whereNotNull('id')->first();
        if (! $business) {
            return ['ok' => false, 'message' => 'Wallet funding is not configured. Please try again later.'];
        }

        $payerName = $renter->verified_account_name ?: $renter->name;
        $req = $request ?? request();

        try {
            $payment = $this->paymentService->createPayment([
                'amount' => $amount,
                'payer_name' => $payerName,
                'webhook_url' => $business->webhook_url ?? '',
                'service' => 'rentals_wallet',
                'business_website_id' => null,
            ], $business, $req, false);

            $payment->update([
                'renter_id' => $renter->id,
                'expires_at' => now()->addHours(24),
            ]);

            $account = $payment->accountNumberDetails
                ?: \App\Models\AccountNumber::where('account_number', $payment->account_number)->first();

            return [
                'ok' => true,
                'payment' => [
                    'transaction_id' => $payment->transaction_id,
                    'amount' => (float) $payment->amount,
                    'account_number' => $payment->account_number,
                    'bank' => $payment->bank,
                    'bank_name' => $account?->bank_name,
                    'account_name' => $account?->account_name,
                ],
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('renter wallet fund payment create failed', [
                'renter_id' => $renter->id,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Could not generate bank details. Try again shortly.'];
        }
    }

    /**
     * @return array{ok: bool, status?: string, wallet_balance?: float, message?: string}
     */
    public function checkFundPayment(Renter $renter, string $transactionId): array
    {
        \Illuminate\Support\Facades\Artisan::call('payment:monitor-emails');

        $payment = Payment::where('transaction_id', $transactionId)
            ->where('renter_id', $renter->id)
            ->first();

        if (! $payment) {
            $candidate = Payment::where('transaction_id', $transactionId)->first();
            if ($candidate && ! $candidate->renter_id) {
                $businessId = Setting::get('wallet_funding_business_id', null);
                $fundingBusiness = $businessId ? Business::find($businessId) : null;
                $payer = strtolower(trim((string) ($candidate->payer_name ?? '')));
                $renterName = strtolower(trim((string) ($renter->verified_account_name ?: $renter->name)));
                $looksLikeWalletFund = (! $candidate->rental_id)
                    && ($fundingBusiness ? (int) $candidate->business_id === (int) $fundingBusiness->id : true)
                    && $payer !== ''
                    && $payer === $renterName
                    && $candidate->created_at
                    && $candidate->created_at->greaterThanOrEqualTo(now()->subDays(2));
                if ($looksLikeWalletFund) {
                    $candidate->update(['renter_id' => $renter->id]);
                    $payment = $candidate->fresh();
                }
            }
        }

        if (! $payment) {
            return ['ok' => false, 'message' => 'No payment found for that reference. Check the transaction ID.'];
        }

        return [
            'ok' => true,
            'status' => $payment->status,
            'wallet_balance' => (float) ($renter->fresh()->wallet_balance ?? 0.0),
        ];
    }
}

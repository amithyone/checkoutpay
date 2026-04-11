<?php

namespace App\Services;

use App\Models\AccountNumber;
use App\Models\Rental;
use App\Models\Payment;
use Illuminate\Support\Str;

class RentalPaymentService
{
    public function __construct(
        protected PaymentService $paymentService,
        protected MevonRubiesVirtualAccountService $mevonRubiesVirtualAccountService
    ) {}

    /**
     * Create rental payments (internal + optional external).
     *
     * Renter pays via the payment page. When external rentals are enabled for the business,
     * we generate:
     * - external: via Mevon Rubies /V1/createrubies (renter VA; may require OTP on WhatsApp Tier 2)
     * - internal: via the existing internal matching flow (account pool)
     *
     * Rental is considered paid when either payment is approved.
     */
    public function createPaymentForRental(Rental $rental): Payment
    {
        $business = $rental->business;
        $amount = (float) $rental->total_amount;
        $payerName = $rental->verified_account_name ?: $rental->renter_name;

        $externalMode = $business->externalProviderModeForService('mevonpay', 'rental');
        $shouldCreateExternal = in_array($externalMode, ['external_only', 'hybrid'], true);
        $mevonPayVaGenerationMode = $business->externalProviderVaGenerationMode('mevonpay');

        // When using "temp" VA generation, we can rely on the uniqueness/webhook behavior
        // and avoid creating an extra internal pending payment row.
        // "dynamic" keeps the existing dual-payment behavior.
        $shouldCreateInternalSecondary = $mevonPayVaGenerationMode !== 'temp';

        // If we already created at least one payment, reuse existing records.
        if ($rental->payment_id) {
            // If we already have both payments (or external isn't required), return primary.
            if (! $shouldCreateExternal || $rental->secondary_payment_id) {
                return $rental->payment;
            }

            // If we're intentionally skipping internal secondary (temp mode) and the primary
            // payment is already an external gateway payment, don't create another external row.
            if (! $shouldCreateInternalSecondary) {
                $existingPayment = $rental->payment;
                if ($existingPayment && $existingPayment->isExternalGatewayPayment()) {
                    return $existingPayment->fresh();
                }
            }
            // Otherwise, fall through and create the missing internal/secondary payment.
        }

        // Ensure payment_link_code exists (used by payment URL).
        if (! $rental->payment_link_code) {
            $code = Str::random(32);
            while (Rental::where('payment_link_code', $code)->exists()) {
                $code = Str::random(32);
            }

            $rental->payment_link_code = $code;
            $rental->save();
        }

        $internalPayment = null;
        $externalPayment = null;

        // Backward compatibility:
        // If payment_id is already an external-gateway payment but secondary_payment_id is empty,
        // we only need to create the missing internal secondary option.
        if (
            $shouldCreateInternalSecondary
            && $rental->payment_id
            && ! $rental->secondary_payment_id
            && $shouldCreateExternal
        ) {
            $existingPayment = $rental->payment;
            if ($existingPayment && $existingPayment->isExternalGatewayPayment()) {
                $internalPayment = $this->paymentService->createPayment([
                    'amount' => $amount,
                    'payer_name' => $payerName,
                    'webhook_url' => $business->webhook_url ?? '',
                    'service' => 'rental',
                    'business_website_id' => null,
                    'external_override' => 'internal_only',
                ], $business, request(), false);

                $internalPayment->update([
                    'rental_id' => $rental->id,
                    'expires_at' => now()->addHours(48),
                ]);

                $emailData = $internalPayment->email_data ?? [];
                $emailData['rental_id'] = $rental->id;
                $emailData['rental_number'] = $rental->rental_number;
                $internalPayment->update(['email_data' => $emailData]);

                $rental->update([
                    'secondary_payment_id' => $internalPayment->id,
                ]);

                return $existingPayment->fresh();
            }
        }

        // 1) Internal option (always created for rental users when not previously set)
        if (! $rental->payment_id && $shouldCreateInternalSecondary) {
            // We'll set rental->payment_id to external later (if created). For now create internal.
            $internalPayment = $this->paymentService->createPayment([
                'amount' => $amount,
                'payer_name' => $payerName,
                'webhook_url' => $business->webhook_url ?? '',
                'service' => 'rental',
                'business_website_id' => null,
                // Force internal flow for the "internal" account option.
                'external_override' => 'internal_only',
            ], $business, request(), false);

            $internalPayment->update([
                'rental_id' => $rental->id,
                'expires_at' => now()->addHours(48),
            ]);

            $emailData = $internalPayment->email_data ?? [];
            $emailData['rental_id'] = $rental->id;
            $emailData['rental_number'] = $rental->rental_number;
            $internalPayment->update(['email_data' => $emailData]);
        } elseif ($shouldCreateInternalSecondary && ! $rental->secondary_payment_id && $shouldCreateExternal) {
            // Rental already has primary payment_id, but external requested; assume the primary is internal.
            $internalPayment = $rental->payment;
        }

        // 2) External option (via MEVONRUBIES)
        if ($shouldCreateExternal && ! $rental->secondary_payment_id) {
            // If payment_id is already set and we want both, we will treat it as internal.
            if ($shouldCreateInternalSecondary) {
                $internalPayment = $internalPayment ?: $rental->payment;
            }

            try {
                if (! $shouldCreateInternalSecondary) {
                    // Temp-mode means we only want ONE pending payment row.
                    // If an existing primary payment is internal-pending, reject it now
                    // so it can't later be auto-matched.
                    $oldPrimary = $rental->payment;
                    if (
                        $oldPrimary
                        && $oldPrimary->status === Payment::STATUS_PENDING
                        && ! $oldPrimary->isExternalGatewayPayment()
                    ) {
                        $oldPrimary->reject('Replaced by external VA (temp mode).');
                    }
                }

                if (! $this->mevonRubiesVirtualAccountService->isConfigured()) {
                    throw new \RuntimeException('MevonRubies is not configured.');
                }

                $renter = $rental->renter()->first();
                $va = null;

                // Reuse persistent renter Rubies account when available.
                if ($renter && ! empty($renter->rubies_account_number)) {
                    $va = [
                        'account_number' => (string) $renter->rubies_account_number,
                        'account_name' => (string) ($renter->rubies_account_name ?? ''),
                        'bank_name' => (string) ($renter->rubies_bank_name ?? ''),
                        'bank_code' => (string) ($renter->rubies_bank_code ?? ''),
                        'reference' => (string) ($renter->rubies_reference ?? ''),
                    ];
                } else {
                    if (! $renter) {
                        throw new \RuntimeException('Rental renter profile not found for MEVONRUBIES.');
                    }

                    // Create once and persist on renter profile.
                    $va = $this->mevonRubiesVirtualAccountService->createRenterAccount($renter);
                    $renter->update([
                        'rubies_account_number' => $va['account_number'] ?? null,
                        'rubies_account_name' => $va['account_name'] ?? null,
                        'rubies_bank_name' => $va['bank_name'] ?? null,
                        'rubies_bank_code' => $va['bank_code'] ?? null,
                        'rubies_reference' => $va['reference'] ?? null,
                        'rubies_account_created_at' => now(),
                    ]);
                }

                // Store account details for display.
                AccountNumber::updateOrCreate(
                    ['account_number' => $va['account_number']],
                    [
                        'account_name' => $va['account_name'] ?: ($payerName ?: ''),
                        'bank_name' => $va['bank_name'] ?: '',
                        'business_id' => $business->id,
                        'business_website_id' => null,
                        'is_pool' => false,
                        'is_external' => true,
                        'external_provider' => 'mevonrubies',
                        'is_active' => true,
                    ]
                );

                // Create external payment record (webhook-driven).
                $txn = 'TXN-' . strtoupper(Str::random(10));
                while (Payment::where('transaction_id', $txn)->exists()) {
                    $txn = 'TXN-' . strtoupper(Str::random(10));
                }

                $externalPayment = Payment::create([
                    'transaction_id' => $txn,
                    'amount' => $amount,
                    'payer_name' => $payerName,
                    'bank' => $va['bank_name'] ?? null,
                    'webhook_url' => $business->webhook_url ?? '',
                    'account_number' => $va['account_number'],
                    'business_id' => $business->id,
                    'business_website_id' => null,
                    'rental_id' => $rental->id,
                    'status' => Payment::STATUS_PENDING,
                    'payment_source' => Payment::SOURCE_EXTERNAL_MEVONPAY,
                    'external_reference' => !empty($va['reference']) ? $va['reference'] : null,
                    'email_data' => array_filter([
                        'service' => 'rental',
                        'skip_auto_match' => true,
                    ]),
                    'expires_at' => now()->addHours(48),
                ]);

                $emailData = $externalPayment->email_data ?? [];
                $emailData['rental_id'] = $rental->id;
                $emailData['rental_number'] = $rental->rental_number;
                $externalPayment->update(['email_data' => $emailData]);

                // For UI: primary = external, secondary = internal.
                $rental->update([
                    'payment_id' => $externalPayment->id,
                    'secondary_payment_id' => $shouldCreateInternalSecondary ? $internalPayment?->id : null,
                    'payment_link_code' => $rental->payment_link_code,
                ]);
            } catch (\Throwable $e) {
                // If external fails, keep the internal payment only.
                \Illuminate\Support\Facades\Log::error('Failed to create external rental payment', [
                    'rental_id' => $rental->id,
                    'error' => $e->getMessage(),
                ]);

                // Ensure rental->payment_id points to the internal payment.
                if (! $internalPayment) {
                    $internalPayment = $this->paymentService->createPayment([
                        'amount' => $amount,
                        'payer_name' => $payerName,
                        'webhook_url' => $business->webhook_url ?? '',
                        'service' => 'rental',
                        'business_website_id' => null,
                        // Force internal flow for the "internal" account option.
                        'external_override' => 'internal_only',
                    ], $business, request(), false);

                    $internalPayment->update([
                        'rental_id' => $rental->id,
                        'expires_at' => now()->addHours(48),
                    ]);

                    $emailData = $internalPayment->email_data ?? [];
                    $emailData['rental_id'] = $rental->id;
                    $emailData['rental_number'] = $rental->rental_number;
                    $internalPayment->update(['email_data' => $emailData]);
                }

                $rental->update([
                    'payment_id' => $internalPayment->id,
                    'secondary_payment_id' => null,
                    'payment_link_code' => $rental->payment_link_code,
                ]);

                return $internalPayment->fresh();
            }
        } else {
            // No external requested (or already exists). Ensure rental points to internal.
            if ($internalPayment) {
                $rental->update([
                    'payment_id' => $internalPayment->id,
                    'secondary_payment_id' => null,
                    'payment_link_code' => $rental->payment_link_code,
                ]);
                return $internalPayment->fresh();
            }
        }

        return $externalPayment ?: $rental->payment->fresh();
    }
}

<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Setting;

class ChargeService
{
    /**
     * Default charge percentage (1%)
     */
    const DEFAULT_PERCENTAGE = 1.0;

    /**
     * Default fixed charge (100)
     */
    const DEFAULT_FIXED = 100.0;

    /**
     * Calculate charges for a payment amount
     *
     * @param float $amount Original payment amount
     * @param Business|null $business Business model (optional, for custom charges)
     * @return array
     */
    public function calculateCharges(float $amount, ?Business $business = null): array
    {
        // Get charge settings
        $percentage = $this->getChargePercentage($business);
        $fixed = $this->getChargeFixed($business);
        $isExempt = $this->isChargeExempt($business);
        $paidByCustomer = $this->isPaidByCustomer($business);

        // If business is exempt, no charges
        if ($isExempt) {
            return [
                'original_amount' => $amount,
                'charge_percentage' => 0,
                'charge_fixed' => 0,
                'total_charges' => 0,
                'amount_to_pay' => $amount,
                'business_receives' => $amount,
                'paid_by_customer' => false,
                'exempt' => true,
            ];
        }

        // Calculate charges
        $percentageCharge = ($amount * $percentage) / 100;
        $totalCharges = $percentageCharge + $fixed;

        if ($paidByCustomer) {
            // Customer pays charges - add to amount
            return [
                'original_amount' => $amount,
                'charge_percentage' => round($percentageCharge, 2),
                'charge_fixed' => $fixed,
                'total_charges' => round($totalCharges, 2),
                'amount_to_pay' => round($amount + $totalCharges, 2),
                'business_receives' => $amount,
                'paid_by_customer' => true,
                'exempt' => false,
            ];
        } else {
            // Business pays charges - deduct from amount
            return [
                'original_amount' => $amount,
                'charge_percentage' => round($percentageCharge, 2),
                'charge_fixed' => $fixed,
                'total_charges' => round($totalCharges, 2),
                'amount_to_pay' => $amount,
                'business_receives' => round($amount - $totalCharges, 2),
                'paid_by_customer' => false,
                'exempt' => false,
            ];
        }
    }

    /**
     * Get charge percentage for business
     *
     * @param Business|null $business
     * @return float
     */
    public function getChargePercentage(?Business $business = null): float
    {
        // Check if business has custom percentage
        if ($business && $business->charge_percentage !== null) {
            return (float) $business->charge_percentage;
        }

        // Get default from settings
        return (float) Setting::get('default_charge_percentage', self::DEFAULT_PERCENTAGE);
    }

    /**
     * Get fixed charge for business
     *
     * @param Business|null $business
     * @return float
     */
    public function getChargeFixed(?Business $business = null): float
    {
        // Check if business has custom fixed charge
        if ($business && $business->charge_fixed !== null) {
            return (float) $business->charge_fixed;
        }

        // Get default from settings
        return (float) Setting::get('default_charge_fixed', self::DEFAULT_FIXED);
    }

    /**
     * Check if business is exempt from charges
     *
     * @param Business|null $business
     * @return bool
     */
    public function isChargeExempt(?Business $business = null): bool
    {
        if (!$business) {
            return false;
        }

        return (bool) $business->charge_exempt;
    }

    /**
     * Check if charges are paid by customer
     *
     * @param Business|null $business
     * @return bool
     */
    public function isPaidByCustomer(?Business $business = null): bool
    {
        if (!$business) {
            return false;
        }

        return (bool) $business->charges_paid_by_customer;
    }
}

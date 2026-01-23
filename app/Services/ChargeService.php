<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessWebsite;
use App\Models\Setting;

class ChargeService
{
    /**
     * Default charge percentage (1%)
     */
    const DEFAULT_PERCENTAGE = 1.0;

    /**
     * Default fixed charge (50)
     */
    const DEFAULT_FIXED = 50.0;

    /**
     * Calculate charges for a payment amount
     *
     * @param float $amount Original payment amount
     * @param BusinessWebsite|null $website Website model (optional, for custom charges)
     * @param Business|null $business Business model (fallback if website not provided)
     * @return array
     */
    public function calculateCharges(float $amount, ?BusinessWebsite $website = null, ?Business $business = null): array
    {
        // Get charge settings - prioritize website over business
        $percentage = $this->getChargePercentage($website, $business);
        $fixed = $this->getChargeFixed($website, $business);
        $isExempt = $this->isChargeExempt($website, $business);
        $paidByCustomer = $this->isPaidByCustomer($website, $business);

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
     * Get charge percentage - website-based (default: 1%), no fallback to business
     *
     * @param BusinessWebsite|null $website
     * @param Business|null $business (kept for backward compatibility, not used)
     * @return float
     */
    public function getChargePercentage(?BusinessWebsite $website = null, ?Business $business = null): float
    {
        // Check if website has custom percentage
        if ($website && $website->charge_percentage !== null) {
            return (float) $website->charge_percentage;
        }

        // Default: 1% (website-based charges, no fallback to business)
        return (float) Setting::get('default_charge_percentage', self::DEFAULT_PERCENTAGE);
    }

    /**
     * Get fixed charge - website-based (default: 100), no fallback to business
     *
     * @param BusinessWebsite|null $website
     * @param Business|null $business (kept for backward compatibility, not used)
     * @return float
     */
    public function getChargeFixed(?BusinessWebsite $website = null, ?Business $business = null): float
    {
        // Check if website has custom fixed charge
        if ($website && $website->charge_fixed !== null) {
            return (float) $website->charge_fixed;
        }

        // Default: 100 (website-based charges, no fallback to business)
        return (float) Setting::get('default_charge_fixed', self::DEFAULT_FIXED);
    }

    /**
     * Check if website/business is exempt from charges
     *
     * @param BusinessWebsite|null $website
     * @param Business|null $business
     * @return bool
     */
    public function isChargeExempt(?BusinessWebsite $website = null, ?Business $business = null): bool
    {
        // Website-level exemption (if implemented in future)
        // For now, check business-level exemption
        if ($business && $business->charge_exempt) {
            return true;
        }

        return false;
    }

    /**
     * Check if charges are paid by customer - website-based (default: false)
     *
     * @param BusinessWebsite|null $website
     * @param Business|null $business (kept for backward compatibility, not used)
     * @return bool
     */
    public function isPaidByCustomer(?BusinessWebsite $website = null, ?Business $business = null): bool
    {
        // Check if website has custom setting
        if ($website && $website->charges_paid_by_customer !== null) {
            return (bool) $website->charges_paid_by_customer;
        }

        // Default: business pays charges (false)
        return false;
    }
}

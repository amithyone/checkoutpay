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
        // Check if charges are enabled for website
        $chargesEnabled = $this->areChargesEnabled($website, $business);
        
        // Get charge settings - prioritize website over business
        $percentage = $this->getChargePercentage($website, $business);
        $fixed = $this->getChargeFixed($website, $business);
        $isExempt = $this->isChargeExempt($website, $business);
        $paidByCustomer = $this->isPaidByCustomer($website, $business);

        // If charges are disabled or business is exempt, no charges
        if (!$chargesEnabled || $isExempt) {
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
                'business_receives' => $this->roundBusinessReceives($amount),
                'paid_by_customer' => true,
                'exempt' => false,
            ];
        } else {
            // Business pays charges - deduct from amount and round business receives
            $businessReceives = $amount - $totalCharges;
            $roundedBusinessReceives = $this->roundBusinessReceives($businessReceives);
            
            // Adjust total charges to match rounded business receives
            $adjustedTotalCharges = $amount - $roundedBusinessReceives;
            
            return [
                'original_amount' => $amount,
                'charge_percentage' => round($percentageCharge, 2),
                'charge_fixed' => $fixed,
                'total_charges' => round($adjustedTotalCharges, 2),
                'amount_to_pay' => $amount,
                'business_receives' => $roundedBusinessReceives,
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
     * Check if charges are enabled for website
     *
     * @param BusinessWebsite|null $website
     * @param Business|null $business
     * @return bool
     */
    public function areChargesEnabled(?BusinessWebsite $website = null, ?Business $business = null): bool
    {
        // Website-level charges enabled (default: true)
        if ($website) {
            return $website->charges_enabled ?? true;
        }

        // If no website, check business-level exemption
        if ($business && $business->charge_exempt) {
            return false;
        }

        // Default: charges enabled
        return true;
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

    /**
     * Calculate charges for invoice payment
     * Invoices are free by default, but charges apply when amount exceeds threshold
     *
     * @param float $amount Invoice amount
     * @param Business|null $business Business that owns the invoice
     * @return array
     */
    public function calculateInvoiceCharges(float $amount, ?Business $business = null): array
    {
        // Check if invoice charges are enabled
        $chargesEnabled = Setting::get('invoice_charges_enabled', false);
        $threshold = Setting::get('invoice_charge_threshold', 0);
        
        // If charges disabled or amount below threshold, no charges
        if (!$chargesEnabled || $amount <= $threshold) {
            return [
                'original_amount' => $amount,
                'charge_percentage' => 0,
                'charge_fixed' => 0,
                'total_charges' => 0,
                'business_receives' => $amount,
                'exempt' => true,
            ];
        }

        // Get invoice charge settings
        $percentage = (float) Setting::get('invoice_charge_percentage', 0);
        $fixed = (float) Setting::get('invoice_charge_fixed', 0);

        // Calculate charges
        $percentageCharge = ($amount * $percentage) / 100;
        $totalCharges = $percentageCharge + $fixed;
        $businessReceives = $amount - $totalCharges;

        return [
            'original_amount' => $amount,
            'charge_percentage' => round($percentageCharge, 2),
            'charge_fixed' => $fixed,
            'total_charges' => round($totalCharges, 2),
            'business_receives' => max(0, round($businessReceives, 2)),
            'exempt' => false,
        ];
    }

    /**
     * Round business receives to nearest nice round number
     * Rounds to nearest 500 for amounts >= 1000, nearest 100 for amounts < 1000
     *
     * @param float $amount
     * @return float
     */
    protected function roundBusinessReceives(float $amount): float
    {
        if ($amount >= 1000) {
            // Round to nearest 500 for amounts >= 1000
            return round($amount / 500) * 500;
        } else {
            // Round to nearest 100 for amounts < 1000
            return round($amount / 100) * 100;
        }
    }
}

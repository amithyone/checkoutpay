<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessWebsite;
use App\Models\Payment;
use App\Models\WebsiteRevenueDaily;
use Illuminate\Support\Facades\Log;

class RevenueService
{
    /**
     * Record a transaction and update business revenue
     * 
     * @param Payment $payment The approved payment
     * @param float $amount The amount to record (business_receives after charges)
     */
    public function recordTransaction(Payment $payment, float $amount): void
    {
        if (!$payment->business_id) {
            return;
        }

        $business = $payment->business;

        // Update website-level revenue if payment has a website
        if ($payment->business_website_id) {
            $website = BusinessWebsite::find($payment->business_website_id);
            if ($website) {
                $this->updateWebsiteRevenue($website, $amount);
            }
        } else {
            // If no website, update business revenue directly (legacy support)
            $this->updateRevenue($business, $amount);
        }

        // Recalculate business revenue from all websites to ensure it's always the sum
        $this->recalculateBusinessRevenueFromWebsites($business);
    }

    /**
     * Update business revenue (daily, monthly, yearly)
     * 
     * @param Business $business
     * @param float $amount Amount to add
     */
    public function updateRevenue(Business $business, float $amount): void
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $thisMonth = $now->copy()->startOfMonth();
        $thisYear = $now->copy()->startOfYear();

        // Update daily revenue
        if (!$business->last_daily_revenue_update || $business->last_daily_revenue_update->lt($today)) {
            // If last update was before today, reset to 0 and start fresh
            $business->daily_revenue = $amount;
        } else {
            // Continue counting from current value
            $business->daily_revenue = ($business->daily_revenue ?? 0) + $amount;
        }
        $business->last_daily_revenue_update = $now;

        // Update monthly revenue
        if (!$business->last_monthly_revenue_update || $business->last_monthly_revenue_update->lt($thisMonth)) {
            // If last update was before this month, reset to 0 and start fresh
            $business->monthly_revenue = $amount;
        } else {
            // Continue counting from current value
            $business->monthly_revenue = ($business->monthly_revenue ?? 0) + $amount;
        }
        $business->last_monthly_revenue_update = $now;

        // Update yearly revenue
        if (!$business->last_yearly_revenue_update || $business->last_yearly_revenue_update->lt($thisYear)) {
            // If last update was before this year, reset to 0 and start fresh
            $business->yearly_revenue = $amount;
        } else {
            // Continue counting from current value
            $business->yearly_revenue = ($business->yearly_revenue ?? 0) + $amount;
        }
        $business->last_yearly_revenue_update = $now;

        $business->save();

        Log::info('Business revenue updated', [
            'business_id' => $business->id,
            'amount' => $amount,
            'daily_revenue' => $business->daily_revenue,
            'monthly_revenue' => $business->monthly_revenue,
            'yearly_revenue' => $business->yearly_revenue,
        ]);
    }


    /**
     * Update website revenue (daily, monthly, yearly)
     * 
     * @param BusinessWebsite $website
     * @param float $amount Amount to add
     */
    public function updateWebsiteRevenue(BusinessWebsite $website, float $amount): void
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $thisMonth = $now->copy()->startOfMonth();
        $thisYear = $now->copy()->startOfYear();
        $todayDate = $now->toDateString();

        // Update daily revenue
        $dailyRevenue = WebsiteRevenueDaily::firstOrNew([
            'business_website_id' => $website->id,
            'revenue_date' => $todayDate,
        ]);

        // If this is a new record for today, start fresh
        // Otherwise, continue counting from current value (even if manually edited)
        if (!$dailyRevenue->exists) {
            $dailyRevenue->revenue = $amount;
        } else {
            // Continue counting from current value (allows super admin edits to persist)
            $dailyRevenue->revenue = ($dailyRevenue->revenue ?? 0) + $amount;
        }
        $dailyRevenue->last_updated_at = $now;
        $dailyRevenue->save();

        // Recalculate monthly and yearly revenue from all daily records
        // This ensures consistency when daily revenues are manually edited
        $this->recalculateWebsiteRevenueFromDaily($website);

        Log::info('Website revenue updated', [
            'website_id' => $website->id,
            'amount' => $amount,
            'daily_revenue' => $dailyRevenue->revenue,
            'monthly_revenue' => $website->monthly_revenue,
            'yearly_revenue' => $website->yearly_revenue,
        ]);
    }


    /**
     * Recalculate website monthly and yearly revenue from all daily revenue records
     * 
     * @param BusinessWebsite $website
     */
    public function recalculateWebsiteRevenueFromDaily(BusinessWebsite $website): void
    {
        $now = now();
        $thisMonth = $now->copy()->startOfMonth();
        $thisYear = $now->copy()->startOfYear();

        // Calculate monthly revenue: sum of all daily revenues for current month
        $monthlyRevenue = WebsiteRevenueDaily::where('business_website_id', $website->id)
            ->whereYear('revenue_date', $thisMonth->year)
            ->whereMonth('revenue_date', $thisMonth->month)
            ->sum('revenue') ?? 0;
        
        $website->monthly_revenue = $monthlyRevenue;
        $website->last_monthly_revenue_update = $now;

        // Calculate yearly revenue: sum of all daily revenues for current year
        $yearlyRevenue = WebsiteRevenueDaily::where('business_website_id', $website->id)
            ->whereYear('revenue_date', $thisYear->year)
            ->sum('revenue') ?? 0;
        
        $website->yearly_revenue = $yearlyRevenue;
        $website->last_yearly_revenue_update = $now;

        $website->save();

        Log::info('Website revenue recalculated from daily records', [
            'website_id' => $website->id,
            'monthly_revenue' => $monthlyRevenue,
            'yearly_revenue' => $yearlyRevenue,
        ]);
    }

    /**
     * Recalculate business revenue from all website revenues
     * Business daily/monthly/yearly revenue = sum of all website revenues
     * 
     * @param Business $business
     */
    public function recalculateBusinessRevenueFromWebsites(Business $business): void
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $todayDate = $now->toDateString();
        $thisMonth = $now->copy()->startOfMonth();
        $thisYear = $now->copy()->startOfYear();

        // Calculate daily revenue: sum of all website daily revenues for today
        $dailyRevenue = 0;
        foreach ($business->websites as $website) {
            $websiteDailyRevenue = $website->getDailyRevenueForDate($todayDate);
            if ($websiteDailyRevenue) {
                $dailyRevenue += $websiteDailyRevenue->revenue;
            }
        }
        $business->daily_revenue = $dailyRevenue;
        $business->last_daily_revenue_update = $now;

        // Calculate monthly revenue: sum of all website monthly revenues
        $monthlyRevenue = $business->websites()->sum('monthly_revenue') ?? 0;
        $business->monthly_revenue = $monthlyRevenue;
        $business->last_monthly_revenue_update = $now;

        // Calculate yearly revenue: sum of all website yearly revenues
        $yearlyRevenue = $business->websites()->sum('yearly_revenue') ?? 0;
        $business->yearly_revenue = $yearlyRevenue;
        $business->last_yearly_revenue_update = $now;

        $business->save();

        Log::info('Business revenue recalculated from websites', [
            'business_id' => $business->id,
            'daily_revenue' => $dailyRevenue,
            'monthly_revenue' => $monthlyRevenue,
            'yearly_revenue' => $yearlyRevenue,
        ]);
    }
}

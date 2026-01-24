<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessWebsite;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SuperAdminBusinessService
{
    /**
     * Get or create the super admin business
     * Website: check-outpay.com
     * 
     * @return Business
     */
    public static function getOrCreateSuperAdminBusiness(): Business
    {
        $email = 'admin@check-outpay.com';
        $websiteUrl = 'https://check-outpay.com';

        // Try to find existing super admin business
        $business = Business::where('email', $email)->first();

        if (!$business) {
            // Create super admin business
            $business = Business::create([
                'name' => 'CheckoutPay Admin',
                'email' => $email,
                'password' => bcrypt(Str::random(32)), // Random password, won't be used for login
                'is_active' => true,
                'balance' => 0,
                'email_verified_at' => now(),
            ]);

            Log::info('Super admin business created', [
                'business_id' => $business->id,
                'email' => $email,
            ]);
        }

        // Ensure website exists
        $website = BusinessWebsite::where('business_id', $business->id)
            ->where('website_url', $websiteUrl)
            ->first();

        if (!$website) {
            $website = BusinessWebsite::create([
                'business_id' => $business->id,
                'website_url' => $websiteUrl,
                'is_approved' => true,
                'approved_at' => now(),
            ]);

            Log::info('Super admin business website created', [
                'business_id' => $business->id,
                'website_id' => $website->id,
                'website_url' => $websiteUrl,
            ]);
        }

        return $business;
    }

    /**
     * Get super admin business website
     * 
     * @return BusinessWebsite|null
     */
    public static function getSuperAdminWebsite(): ?BusinessWebsite
    {
        $business = self::getOrCreateSuperAdminBusiness();
        return BusinessWebsite::where('business_id', $business->id)
            ->where('website_url', 'https://check-outpay.com')
            ->first();
    }
}

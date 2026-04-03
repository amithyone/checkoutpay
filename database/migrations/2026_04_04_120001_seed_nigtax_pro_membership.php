<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inserts the NigTax PRO monthly membership when the NigTax payment business exists.
     * Uses the same business as certified VA payments (NIGTAX_PAYMENT_BUSINESS_ID).
     */
    public function up(): void
    {
        if (! Schema::hasTable('memberships') || ! Schema::hasTable('businesses')) {
            return;
        }

        $businessId = (int) config('services.nigtax.payment_business_id', 0);
        if ($businessId < 1 || ! DB::table('businesses')->where('id', $businessId)->exists()) {
            return;
        }

        $slug = (string) config('services.nigtax.pro_membership_slug', 'nigtax-pro');
        if (DB::table('memberships')->where('slug', $slug)->exists()) {
            return;
        }

        DB::table('memberships')->insert([
            'business_id' => $businessId,
            'category_id' => null,
            'name' => 'NigTax PRO',
            'slug' => $slug,
            'description' => 'Monthly PRO on NigTax: sign in to combine multiple bank statements and set a separate PDF password for each file.',
            'who_is_it_for' => 'Users who upload more than one statement or use different PDF passwords per file.',
            'who_is_it_for_suggestions' => null,
            'price' => 2000.00,
            'currency' => 'NGN',
            'duration_type' => 'months',
            'duration_value' => 1,
            'features' => json_encode([
                'Multiple statement uploads in one run',
                'Individual PDF password per statement',
                'Same email as checkout for activation',
            ]),
            'images' => null,
            'terms_and_conditions' => null,
            'is_active' => true,
            'is_featured' => false,
            'max_members' => null,
            'current_members' => 0,
            'city' => null,
            'is_global' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('memberships')) {
            return;
        }

        $slug = (string) config('services.nigtax.pro_membership_slug', 'nigtax-pro');
        DB::table('memberships')->where('slug', $slug)->delete();
    }
};

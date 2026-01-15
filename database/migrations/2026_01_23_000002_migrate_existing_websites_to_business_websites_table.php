<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing website data from businesses table to business_websites table
        $businesses = DB::table('businesses')
            ->whereNotNull('website')
            ->get();

        foreach ($businesses as $business) {
            DB::table('business_websites')->insert([
                'business_id' => $business->id,
                'website_url' => $business->website,
                'is_approved' => $business->website_approved ?? false,
                'created_at' => $business->created_at ?? now(),
                'updated_at' => $business->updated_at ?? now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is data migration only, no schema changes to reverse
        // The data will remain in business_websites table
    }
};

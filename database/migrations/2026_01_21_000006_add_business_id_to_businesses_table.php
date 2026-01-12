<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('business_id', 5)->unique()->nullable()->after('id');
            $table->index('business_id');
        });

        // Generate business IDs for existing businesses
        $existingBusinesses = DB::table('businesses')->whereNull('business_id')->get();
        foreach ($existingBusinesses as $business) {
            $businessId = $this->generateUniqueBusinessId();
            DB::table('businesses')
                ->where('id', $business->id)
                ->update(['business_id' => $businessId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['business_id']);
            $table->dropColumn('business_id');
        });
    }

    /**
     * Generate a unique 5-character business ID
     */
    private function generateUniqueBusinessId(): string
    {
        do {
            // Generate 5 random alphanumeric characters (uppercase letters and numbers)
            $businessId = strtoupper(Str::random(5));
            // Ensure it contains both letters and numbers
            if (!preg_match('/[A-Z]/', $businessId) || !preg_match('/[0-9]/', $businessId)) {
                // If it doesn't have both, regenerate
                continue;
            }
        } while (DB::table('businesses')->where('business_id', $businessId)->exists());

        return $businessId;
    }
};

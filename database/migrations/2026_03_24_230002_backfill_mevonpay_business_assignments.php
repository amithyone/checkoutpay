<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $api = DB::table('external_apis')->where('provider_key', 'mevonpay')->first();
        if (!$api) {
            return;
        }

        $businessIds = DB::table('businesses')
            ->where('uses_external_account_numbers', true)
            ->pluck('id');

        foreach ($businessIds as $businessId) {
            DB::table('business_external_api')->updateOrInsert(
                ['business_id' => $businessId, 'external_api_id' => $api->id],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        $api = DB::table('external_apis')->where('provider_key', 'mevonpay')->first();
        if (!$api) {
            return;
        }

        DB::table('business_external_api')->where('external_api_id', $api->id)->delete();
    }
};

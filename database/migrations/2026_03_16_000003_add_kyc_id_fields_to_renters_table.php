<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('renters', function (Blueprint $table) {
            $table->string('kyc_id_type')->nullable()->after('kyc_id_card_path');
            $table->string('kyc_id_front_path')->nullable()->after('kyc_id_type');
            $table->string('kyc_id_back_path')->nullable()->after('kyc_id_front_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('renters', function (Blueprint $table) {
            $table->dropColumn(['kyc_id_type', 'kyc_id_front_path', 'kyc_id_back_path']);
        });
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumer_device_stepup_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('consumer_device_stepup_sessions', 'stepup_mode')) {
                $table->string('stepup_mode', 32)->nullable()->after('pending_device_label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('consumer_device_stepup_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('consumer_device_stepup_sessions', 'stepup_mode')) {
                $table->dropColumn('stepup_mode');
            }
        });
    }
};

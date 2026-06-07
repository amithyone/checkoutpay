<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_card_requests', function (Blueprint $table) {
            $table->boolean('auto_freeze_on_decline')->default(true)->after('is_frozen');
        });
    }

    public function down(): void
    {
        Schema::table('virtual_card_requests', function (Blueprint $table) {
            $table->dropColumn('auto_freeze_on_decline');
        });
    }
};

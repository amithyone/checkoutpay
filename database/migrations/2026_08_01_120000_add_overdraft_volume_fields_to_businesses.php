<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (! Schema::hasColumn('businesses', 'overdraft_volume_90d')) {
                $table->decimal('overdraft_volume_90d', 15, 2)->default(0)->after('overdraft_eligible');
            }
            if (! Schema::hasColumn('businesses', 'overdraft_volume_tier')) {
                $table->string('overdraft_volume_tier', 20)->nullable()->after('overdraft_volume_90d');
            }
            if (! Schema::hasColumn('businesses', 'overdraft_volume_computed_at')) {
                $table->timestamp('overdraft_volume_computed_at')->nullable()->after('overdraft_volume_tier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            foreach (['overdraft_volume_computed_at', 'overdraft_volume_tier', 'overdraft_volume_90d'] as $col) {
                if (Schema::hasColumn('businesses', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

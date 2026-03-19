<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (! Schema::hasColumn('businesses', 'withdrawal_pin_hash')) {
                $table->string('withdrawal_pin_hash')->nullable()->after('two_factor_secret');
            }
            if (! Schema::hasColumn('businesses', 'withdrawal_pin_set_at')) {
                $table->timestamp('withdrawal_pin_set_at')->nullable()->after('withdrawal_pin_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (Schema::hasColumn('businesses', 'withdrawal_pin_set_at')) {
                $table->dropColumn('withdrawal_pin_set_at');
            }
            if (Schema::hasColumn('businesses', 'withdrawal_pin_hash')) {
                $table->dropColumn('withdrawal_pin_hash');
            }
        });
    }
};


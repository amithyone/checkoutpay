<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mevon_pay_fx_rate_snapshots', function (Blueprint $table) {
            $table->id();
            $table->timestamp('recorded_at')->index();
            $table->decimal('mevon_mid', 12, 4)->nullable();
            $table->decimal('published_mid', 12, 4);
            $table->decimal('sell_rate', 12, 4)->nullable();
            $table->decimal('buy_rate', 12, 4)->nullable();
            $table->string('source', 32)->default('mevon_live');
            $table->decimal('change_abs', 12, 4)->nullable();
            $table->decimal('change_pct', 10, 4)->nullable();
            $table->timestamps();

            $table->index(['recorded_at', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mevon_pay_fx_rate_snapshots');
    }
};

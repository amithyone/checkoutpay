<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sync_outbound_cursors', function (Blueprint $table) {
            $table->string('entity', 64)->primary();
            $table->unsignedBigInteger('last_origin_id')->default(0);
            $table->unsignedBigInteger('max_origin_id')->nullable();
            $table->string('status', 16)->default('backfill'); // backfill | caught_up
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedBigInteger('rows_pushed_total')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_sync_outbound_cursors');
    }
};

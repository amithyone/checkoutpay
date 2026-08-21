<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sync_row_maps', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 64);
            $table->unsignedBigInteger('origin_id');
            $table->unsignedBigInteger('local_id');
            $table->string('natural_key', 191)->nullable();
            $table->timestamps();

            $table->unique(['entity', 'origin_id']);
            $table->index(['entity', 'local_id']);
            $table->index(['entity', 'natural_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_sync_row_maps');
    }
};

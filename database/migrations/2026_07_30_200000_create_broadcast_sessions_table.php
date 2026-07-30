<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('broadcast_sessions')) {
            return;
        }

        Schema::create('broadcast_sessions', function (Blueprint $table) {
            $table->char('session_uuid', 36)->primary();
            $table->string('terminal_id', 64);
            $table->string('status', 16)->default('open');
            $table->unsignedInteger('amount_ngn')->default(0);
            $table->unsignedBigInteger('opened_at');
            $table->unsignedBigInteger('closed_at')->nullable();
            $table->timestamps();

            $table->index(['terminal_id', 'status'], 'idx_broadcast_sessions_terminal_status');

            if (Schema::hasTable('broadcast_terminals')) {
                $table->foreign('terminal_id', 'fk_broadcast_sessions_terminal_v2')
                    ->references('terminal_id')
                    ->on('broadcast_terminals')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_sessions');
    }
};

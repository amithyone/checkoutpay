<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('broadcast_terminals')) {
            Schema::create('broadcast_terminals', function (Blueprint $table) {
                $table->string('terminal_id', 64)->primary();
                $table->string('signing_key', 256);
                $table->string('merchant_name', 128);
                $table->string('bank_name', 64);
                $table->string('bank_name_hash', 128);
                $table->string('masked_account_suffix', 16);
                $table->string('account_number', 10)->nullable();
                $table->string('recipient_bank_code', 6)->nullable();
                $table->unsignedBigInteger('business_id')->nullable()->index();
                $table->boolean('active')->default(true);
                $table->timestamps();

                if (Schema::hasTable('businesses')) {
                    $table->foreign('business_id', 'broadcast_terminals_business_fk')
                        ->references('id')
                        ->on('businesses')
                        ->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('broadcast_used_sessions')) {
            Schema::create('broadcast_used_sessions', function (Blueprint $table) {
                $table->char('session_uuid', 36)->primary();
                $table->string('terminal_id', 64);
                $table->unsignedBigInteger('used_at');
                $table->index('terminal_id', 'idx_broadcast_sessions_terminal');

                $table->foreign('terminal_id', 'fk_broadcast_sessions_terminal')
                    ->references('terminal_id')
                    ->on('broadcast_terminals')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_used_sessions');
        Schema::dropIfExists('broadcast_terminals');
    }
};

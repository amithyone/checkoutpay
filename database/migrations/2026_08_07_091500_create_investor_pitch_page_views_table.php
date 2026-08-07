<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_pitch_page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_pitch_access_id')
                ->constrained('investor_pitch_accesses')
                ->cascadeOnDelete();
            $table->string('page_key', 64);
            $table->string('path', 255);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->index(['investor_pitch_access_id', 'viewed_at']);
            $table->index(['page_key', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_pitch_page_views');
    }
};

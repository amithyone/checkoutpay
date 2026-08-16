<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_hit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);
            $table->string('path', 500);
            $table->string('origin', 500)->nullable();
            $table->string('referer', 500)->nullable();
            $table->string('website_host', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('api_key_hint', 16)->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->boolean('successful');
            $table->string('message', 500)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at']);
            $table->index(['successful', 'created_at']);
            $table->index(['website_host', 'created_at']);
            $table->index(['path', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_hit_logs');
    }
};

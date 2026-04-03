<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nigtax_pro_saved_queries')) {
            return;
        }

        Schema::create('nigtax_pro_saved_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nigtax_pro_user_id')->constrained('nigtax_pro_users')->cascadeOnDelete();
            $table->string('mode', 16);
            $table->json('snapshot');
            $table->string('statement_filename')->nullable();
            $table->string('statement_pdf_path')->nullable();
            $table->timestamps();

            $table->index(['nigtax_pro_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nigtax_pro_saved_queries');
    }
};

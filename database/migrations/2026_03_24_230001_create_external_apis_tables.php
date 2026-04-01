<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_apis', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider_key')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('business_external_api', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('external_api_id')->constrained('external_apis')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['business_id', 'external_api_id']);
        });

        DB::table('external_apis')->insert([
            'name' => 'MEVONPAY',
            'provider_key' => 'mevonpay',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('business_external_api');
        Schema::dropIfExists('external_apis');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renter_id')->nullable()->constrained('renters')->nullOnDelete();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('token')->unique();
            $table->string('platform', 20)->default('web');
            $table->string('device_name')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['renter_id', 'platform']);
            $table->index(['business_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_device_tokens');
    }
};


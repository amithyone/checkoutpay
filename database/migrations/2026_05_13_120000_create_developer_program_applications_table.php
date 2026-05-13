<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('developer_program_applications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('business_id')->nullable();
            $table->string('phone', 64);
            $table->string('email');
            $table->string('whatsapp', 64);
            $table->string('community_preference', 16);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('developer_program_applications');
    }
};

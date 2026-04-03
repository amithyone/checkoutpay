<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nigtax_pro_pending_registrations')) {
            return;
        }

        Schema::create('nigtax_pro_pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('email');
            $table->string('password_hash');
            $table->string('member_name')->nullable();
            $table->timestamps();

            $table->unique('payment_id');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nigtax_pro_pending_registrations');
    }
};

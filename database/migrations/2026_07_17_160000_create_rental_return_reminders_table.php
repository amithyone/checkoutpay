<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rental_return_reminders')) {
            return;
        }

        Schema::create('rental_return_reminders', function (Blueprint $table) {
            $table->id();
            if (Schema::hasTable('rentals')) {
                $table->foreignId('rental_id')->constrained('rentals')->cascadeOnDelete();
            } else {
                $table->unsignedBigInteger('rental_id');
            }
            $table->string('reminder_type', 32);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['rental_id', 'reminder_type']);
            $table->index('reminder_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_return_reminders');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_overdraft_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->timestamp('due_at');
            $table->decimal('amount_due', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('status', 20)->default('pending'); // pending | paid | overdue | cancelled
            $table->timestamps();
            $table->unique(['business_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_overdraft_installments');
    }
};

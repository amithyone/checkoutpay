<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->integer('sort_order')->default(0);
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit')->nullable()->default('unit');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('invoice_id');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};

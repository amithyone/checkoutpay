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
        Schema::create('website_revenue_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_website_id')->constrained('business_websites')->onDelete('cascade');
            $table->date('revenue_date');
            $table->decimal('revenue', 15, 2)->default(0);
            $table->dateTime('last_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['business_website_id', 'revenue_date']);
            $table->index(['business_website_id', 'revenue_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_revenue_daily');
    }
};

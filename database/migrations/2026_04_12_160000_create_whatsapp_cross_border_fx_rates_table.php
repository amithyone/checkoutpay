<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_cross_border_fx_rates', function (Blueprint $table) {
            $table->id();
            $table->char('from_currency', 3);
            $table->char('to_currency', 3);
            $table->decimal('rate', 24, 12);
            $table->timestamps();

            $table->unique(['from_currency', 'to_currency'], 'wa_fx_from_to_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_cross_border_fx_rates');
    }
};

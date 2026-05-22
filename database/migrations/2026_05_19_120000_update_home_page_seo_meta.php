<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pages')
            ->where('slug', 'home')
            ->update([
                'meta_title' => 'CheckoutPay — Affordable & Reliable Payment Gateway in Nigeria',
                'meta_description' => 'Low-cost, reliable payment gateway for Nigerian businesses. Bank transfer matching, virtual accounts, WooCommerce plugin, and transparent fees.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('pages')
            ->where('slug', 'home')
            ->update([
                'meta_title' => 'CheckoutPay',
                'meta_description' => 'Payments for Nigerian businesses.',
                'updated_at' => now(),
            ]);
    }
};

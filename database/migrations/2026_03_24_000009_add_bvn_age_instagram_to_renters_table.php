<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renters', function (Blueprint $table) {
            $table->string('bvn', 11)->nullable()->after('verified_bank_code');
            $table->unsignedTinyInteger('age')->nullable()->after('bvn');
            $table->string('instagram_url')->nullable()->after('age');
        });
    }

    public function down(): void
    {
        Schema::table('renters', function (Blueprint $table) {
            $table->dropColumn(['bvn', 'age', 'instagram_url']);
        });
    }
};

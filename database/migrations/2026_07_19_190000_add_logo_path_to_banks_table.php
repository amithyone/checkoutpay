<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('banks')) {
            Schema::create('banks', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('logo_path')->nullable();
                $table->string('logo_source', 32)->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('banks', function (Blueprint $table) {
            if (! Schema::hasColumn('banks', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('name');
            }
            if (! Schema::hasColumn('banks', 'logo_source')) {
                $table->string('logo_source', 32)->nullable()->after('logo_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('banks')) {
            return;
        }

        Schema::table('banks', function (Blueprint $table) {
            if (Schema::hasColumn('banks', 'logo_source')) {
                $table->dropColumn('logo_source');
            }
            if (Schema::hasColumn('banks', 'logo_path')) {
                $table->dropColumn('logo_path');
            }
        });
    }
};

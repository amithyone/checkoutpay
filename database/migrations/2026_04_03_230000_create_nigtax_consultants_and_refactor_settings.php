<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nigtax_consultants')) {
            return;
        }

        Schema::create('nigtax_consultants', function (Blueprint $table) {
            $table->id();
            $table->string('consultant_name')->nullable();
            $table->string('firm_name')->nullable();
            $table->string('title')->nullable();
            $table->text('bio')->nullable();
            $table->string('license_number')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('signature_image_path')->nullable();
            $table->string('stamp_image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $firstConsultantId = null;

        if (Schema::hasTable('nigtax_consultant_settings')) {
            $row = DB::table('nigtax_consultant_settings')->where('id', 1)->first();
            if ($row) {
                $firstConsultantId = DB::table('nigtax_consultants')->insertGetId([
                    'consultant_name' => $row->consultant_name,
                    'firm_name' => $row->firm_name,
                    'title' => $row->title,
                    'bio' => $row->bio,
                    'license_number' => $row->license_number,
                    'contact_email' => $row->contact_email,
                    'signature_image_path' => $row->signature_image_path,
                    'stamp_image_path' => $row->stamp_image_path,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if ($firstConsultantId === null) {
            $firstConsultantId = DB::table('nigtax_consultants')->insertGetId([
                'consultant_name' => '',
                'firm_name' => '',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('nigtax_consultant_settings', function (Blueprint $table) {
            $table->foreignId('default_consultant_id')
                ->nullable()
                ->after('id')
                ->constrained('nigtax_consultants')
                ->nullOnDelete();
        });

        DB::table('nigtax_consultant_settings')->update(['default_consultant_id' => $firstConsultantId]);

        Schema::table('nigtax_consultant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'consultant_name',
                'firm_name',
                'title',
                'bio',
                'license_number',
                'contact_email',
                'signature_image_path',
                'stamp_image_path',
            ]);
        });

        if (Schema::hasTable('nigtax_certified_orders') && !Schema::hasColumn('nigtax_certified_orders', 'consultant_id')) {
            Schema::table('nigtax_certified_orders', function (Blueprint $table) {
                $table->foreignId('consultant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('nigtax_consultants')
                    ->nullOnDelete();
            });
        }

        DB::table('nigtax_certified_orders')->whereNull('consultant_id')->update(['consultant_id' => $firstConsultantId]);
    }

    public function down(): void
    {
        if (Schema::hasTable('nigtax_certified_orders') && Schema::hasColumn('nigtax_certified_orders', 'consultant_id')) {
            Schema::table('nigtax_certified_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('consultant_id');
            });
        }

        if (Schema::hasTable('nigtax_consultant_settings') && Schema::hasColumn('nigtax_consultant_settings', 'default_consultant_id')) {
            $c = DB::table('nigtax_consultants')->orderBy('id')->first();
            Schema::table('nigtax_consultant_settings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('default_consultant_id');
            });
            Schema::table('nigtax_consultant_settings', function (Blueprint $table) {
                $table->string('consultant_name')->nullable();
                $table->string('firm_name')->nullable();
                $table->string('title')->nullable();
                $table->text('bio')->nullable();
                $table->string('license_number')->nullable();
                $table->string('contact_email')->nullable();
                $table->string('signature_image_path')->nullable();
                $table->string('stamp_image_path')->nullable();
            });
            if ($c) {
                DB::table('nigtax_consultant_settings')->where('id', 1)->update([
                    'consultant_name' => $c->consultant_name,
                    'firm_name' => $c->firm_name,
                    'title' => $c->title,
                    'bio' => $c->bio,
                    'license_number' => $c->license_number,
                    'contact_email' => $c->contact_email,
                    'signature_image_path' => $c->signature_image_path,
                    'stamp_image_path' => $c->stamp_image_path,
                ]);
            }
        }

        Schema::dropIfExists('nigtax_consultants');
    }
};

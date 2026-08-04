<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_account_prefix_rules', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 10);
            $table->string('bank_code', 20);
            $table->string('bank_name', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique('prefix');
            $table->index(['is_active', 'prefix']);
        });

        $rules = config('bank_account_prefixes.rules', []);
        if (! is_array($rules) || $rules === []) {
            return;
        }

        $now = now();
        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $prefix = preg_replace('/\D+/', '', (string) ($rule['prefix'] ?? '')) ?? '';
            $code = trim((string) ($rule['code'] ?? ''));
            $name = trim((string) ($rule['name'] ?? ''));
            if ($prefix === '' || strlen($prefix) < 2 || $code === '') {
                continue;
            }

            DB::table('bank_account_prefix_rules')->insertOrIgnore([
                'prefix' => $prefix,
                'bank_code' => $code,
                'bank_name' => $name !== '' ? $name : null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_account_prefix_rules');
    }
};

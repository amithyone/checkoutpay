<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_savings_goals', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_savings_goals', 'save_type')) {
                $table->string('save_type', 20)->default('flexible')->after('status');
            }
            if (! Schema::hasColumn('wallet_savings_goals', 'target_date')) {
                $table->date('target_date')->nullable()->after('save_type');
            }
            if (! Schema::hasColumn('wallet_savings_goals', 'duration_days')) {
                $table->unsignedSmallInteger('duration_days')->nullable()->after('target_date');
            }
            if (! Schema::hasColumn('wallet_savings_goals', 'collection_mode')) {
                $table->string('collection_mode', 30)->default('manual')->after('duration_days');
            }
            if (! Schema::hasColumn('wallet_savings_goals', 'auto_save_percent')) {
                $table->decimal('auto_save_percent', 5, 2)->nullable()->after('collection_mode');
            }
            if (! Schema::hasColumn('wallet_savings_goals', 'balance_threshold')) {
                $table->decimal('balance_threshold', 14, 2)->nullable()->after('auto_save_percent');
            }
            if (! Schema::hasColumn('wallet_savings_goals', 'ledger_scope')) {
                $table->string('ledger_scope', 20)->default('personal')->after('balance_threshold');
            }
            if (! Schema::hasColumn('wallet_savings_goals', 'auto_save_enabled')) {
                $table->boolean('auto_save_enabled')->default(false)->after('ledger_scope');
            }
            if (! Schema::hasColumn('wallet_savings_goals', 'soft_lock_until')) {
                $table->timestamp('soft_lock_until')->nullable()->after('auto_save_enabled');
            }
            if (! Schema::hasColumn('wallet_savings_goals', 'completion_bonus_percent')) {
                $table->decimal('completion_bonus_percent', 5, 2)->nullable()->after('soft_lock_until');
            }
            if (! Schema::hasColumn('wallet_savings_goals', 'completion_bonus_paid')) {
                $table->boolean('completion_bonus_paid')->default(false)->after('completion_bonus_percent');
            }
        });

        Schema::table('wallet_savings_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_savings_settings', 'strict_save_enabled')) {
                $table->boolean('strict_save_enabled')->default(false)->after('spend_to_save_percent');
            }
            if (! Schema::hasColumn('wallet_savings_settings', 'strict_save_percent')) {
                $table->decimal('strict_save_percent', 5, 2)->default(5)->after('strict_save_enabled');
            }
            if (! Schema::hasColumn('wallet_savings_settings', 'strict_ledger_scope')) {
                $table->string('strict_ledger_scope', 20)->default('personal')->after('strict_save_percent');
            }
            if (! Schema::hasColumn('wallet_savings_settings', 'strict_collection_mode')) {
                $table->string('strict_collection_mode', 30)->default('per_incoming')->after('strict_ledger_scope');
            }
            if (! Schema::hasColumn('wallet_savings_settings', 'strict_balance_threshold')) {
                $table->decimal('strict_balance_threshold', 14, 2)->nullable()->after('strict_collection_mode');
            }
        });

        $hasComposite = collect(DB::select("SHOW INDEX FROM wallet_savings_locks WHERE Key_name = 'wallet_savings_locks_source_goal_unique'"))->isNotEmpty();
        if (! $hasComposite) {
            try {
                DB::statement('ALTER TABLE wallet_savings_locks DROP FOREIGN KEY wallet_savings_locks_source_transaction_id_foreign');
            } catch (\Throwable) {
                // FK may already be absent during partial reruns.
            }

            try {
                DB::statement('ALTER TABLE wallet_savings_locks DROP INDEX wallet_savings_locks_source_transaction_id_unique');
            } catch (\Throwable) {
                // Index may already be absent during partial reruns.
            }

            Schema::table('wallet_savings_locks', function (Blueprint $table) {
                $table->unique(
                    ['source_transaction_id', 'wallet_savings_goal_id'],
                    'wallet_savings_locks_source_goal_unique',
                );
                $table->foreign('source_transaction_id')
                    ->references('id')
                    ->on('whatsapp_wallet_transactions')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $hasComposite = collect(DB::select("SHOW INDEX FROM wallet_savings_locks WHERE Key_name = 'wallet_savings_locks_source_goal_unique'"))->isNotEmpty();
        if ($hasComposite) {
            try {
                DB::statement('ALTER TABLE wallet_savings_locks DROP FOREIGN KEY wallet_savings_locks_source_transaction_id_foreign');
            } catch (\Throwable) {
            }
            Schema::table('wallet_savings_locks', function (Blueprint $table) {
                $table->dropUnique('wallet_savings_locks_source_goal_unique');
            });
            Schema::table('wallet_savings_locks', function (Blueprint $table) {
                $table->unique('source_transaction_id');
                $table->foreign('source_transaction_id')
                    ->references('id')
                    ->on('whatsapp_wallet_transactions')
                    ->nullOnDelete();
            });
        }

        Schema::table('wallet_savings_settings', function (Blueprint $table) {
            foreach ([
                'strict_save_enabled',
                'strict_save_percent',
                'strict_ledger_scope',
                'strict_collection_mode',
                'strict_balance_threshold',
            ] as $col) {
                if (Schema::hasColumn('wallet_savings_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('wallet_savings_goals', function (Blueprint $table) {
            foreach ([
                'save_type',
                'target_date',
                'duration_days',
                'collection_mode',
                'auto_save_percent',
                'balance_threshold',
                'ledger_scope',
                'auto_save_enabled',
                'soft_lock_until',
                'completion_bonus_percent',
                'completion_bonus_paid',
            ] as $col) {
                if (Schema::hasColumn('wallet_savings_goals', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

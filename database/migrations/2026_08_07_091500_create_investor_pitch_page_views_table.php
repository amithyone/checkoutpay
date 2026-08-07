<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL often auto-commits: a prior failed index create can leave the
        // table behind without recording this migration — so create is idempotent.
        if (! Schema::hasTable('investor_pitch_page_views')) {
            Schema::create('investor_pitch_page_views', function (Blueprint $table) {
                $table->id();
                $table->foreignId('investor_pitch_access_id')
                    ->constrained('investor_pitch_accesses')
                    ->cascadeOnDelete();
                $table->string('page_key', 64);
                $table->string('path', 255);
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamp('viewed_at');
                $table->timestamps();

                // MySQL max identifier length is 64; default name exceeds it.
                $table->index(['investor_pitch_access_id', 'viewed_at'], 'ippv_access_viewed_idx');
                $table->index(['page_key', 'viewed_at'], 'ippv_page_viewed_idx');
            });

            return;
        }

        $needAccessIdx = ! $this->indexExists('investor_pitch_page_views', 'ippv_access_viewed_idx');
        $needPageIdx = ! $this->indexExists('investor_pitch_page_views', 'ippv_page_viewed_idx');

        if (! $needAccessIdx && ! $needPageIdx) {
            return;
        }

        Schema::table('investor_pitch_page_views', function (Blueprint $table) use ($needAccessIdx, $needPageIdx) {
            if ($needAccessIdx) {
                $table->index(['investor_pitch_access_id', 'viewed_at'], 'ippv_access_viewed_idx');
            }
            if ($needPageIdx) {
                $table->index(['page_key', 'viewed_at'], 'ippv_page_viewed_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_pitch_page_views');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(DB::select(
            'SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?',
            [$indexName]
        ))->isNotEmpty();
    }
};

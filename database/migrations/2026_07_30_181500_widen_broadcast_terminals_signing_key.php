<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('broadcast_terminals') || ! Schema::hasColumn('broadcast_terminals', 'signing_key')) {
            return;
        }

        // Encrypted Ed25519 private keys exceed varchar(256); HMAC secrets still fit in TEXT.
        DB::statement('ALTER TABLE broadcast_terminals MODIFY signing_key TEXT NOT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('broadcast_terminals') || ! Schema::hasColumn('broadcast_terminals', 'signing_key')) {
            return;
        }

        DB::statement('ALTER TABLE broadcast_terminals MODIFY signing_key VARCHAR(256) NOT NULL');
    }
};

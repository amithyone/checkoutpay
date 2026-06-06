<?php

use Database\Seeders\LegalPagesDefinitions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        LegalPagesDefinitions::syncToDatabase();
    }

    public function down(): void
    {
        // Legal page content is forward-only; re-run sync after restoring HTML if needed.
    }
};

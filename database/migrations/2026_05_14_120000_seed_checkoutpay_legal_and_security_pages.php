<?php

use Database\Seeders\LegalPagesDefinitions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        LegalPagesDefinitions::syncToDatabase();
    }
};

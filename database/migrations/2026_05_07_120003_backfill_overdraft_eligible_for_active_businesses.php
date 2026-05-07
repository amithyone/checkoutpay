<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('businesses')
            ->where('is_active', true)
            ->update(['overdraft_eligible' => true]);
    }

    public function down(): void
    {
        //
    }
};

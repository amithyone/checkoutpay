<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renters', function (Blueprint $table) {
            $table->enum('kyc_id_status', ['pending', 'approved', 'rejected'])->nullable()->after('kyc_id_back_path');
            $table->timestamp('kyc_id_reviewed_at')->nullable()->after('kyc_id_status');
            $table->unsignedBigInteger('kyc_id_reviewed_by')->nullable()->after('kyc_id_reviewed_at');
            $table->text('kyc_id_rejection_reason')->nullable()->after('kyc_id_reviewed_by');

            $table->index(['kyc_id_status', 'kyc_id_reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('renters', function (Blueprint $table) {
            $table->dropIndex(['kyc_id_status', 'kyc_id_reviewed_at']);
            $table->dropColumn(['kyc_id_status', 'kyc_id_reviewed_at', 'kyc_id_reviewed_by', 'kyc_id_rejection_reason']);
        });
    }
};


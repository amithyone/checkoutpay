<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->json('audiences');
            $table->boolean('channel_email')->default(true);
            $table->boolean('channel_push')->default(true);
            $table->string('push_screen', 32)->nullable();
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('recipients_estimated')->default(0);
            $table->unsignedInteger('emails_sent')->default(0);
            $table->unsignedInteger('emails_failed')->default(0);
            $table->unsignedInteger('emails_skipped')->default(0);
            $table->unsignedInteger('pushes_sent')->default(0);
            $table->unsignedInteger('pushes_failed')->default(0);
            $table->unsignedInteger('pushes_skipped')->default(0);
            $table->text('error_summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_announcements');
    }
};

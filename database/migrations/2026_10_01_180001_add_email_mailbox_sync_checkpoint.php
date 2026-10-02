<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_mailbox_connections', function (Blueprint $table): void {
            $table->json('sync_checkpoint')->nullable();
            $table->string('sync_status', 20)->default('pending');
            $table->string('initial_sync_status', 20)->default('pending');
            $table->unsignedInteger('initial_sync_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('email_mailbox_connections', function (Blueprint $table): void {
            $table->dropColumn(['sync_checkpoint', 'sync_status', 'initial_sync_status', 'initial_sync_count']);
        });
    }
};

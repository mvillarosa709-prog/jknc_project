<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_activities', function (Blueprint $table): void {
            $table->unique(['mailbox_connection_id', 'idempotency_key'], 'email_activities_mailbox_idempotency_unique');
            $table->dropUnique('email_activities_idempotency_unique');
        });
    }

    public function down(): void
    {
        // Live use can legitimately reuse a key across mailboxes. Refuse a lossy rollback.
        if (DB::table('email_activities')->whereNotNull('idempotency_key')
            ->groupBy('idempotency_key')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Mailbox idempotency has cross-mailbox keys; forward-fix instead of rollback.');
        }
        Schema::table('email_activities', function (Blueprint $table): void {
            $table->unique('idempotency_key', 'email_activities_idempotency_unique');
            $table->dropUnique('email_activities_mailbox_idempotency_unique');
        });
    }
};

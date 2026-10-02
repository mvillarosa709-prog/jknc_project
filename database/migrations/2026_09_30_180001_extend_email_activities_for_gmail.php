<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_activities', function (Blueprint $table): void {
            $table->foreignId('mailbox_connection_id')->nullable()->after('mailbox_owner_user_id')->constrained('email_mailbox_connections')->nullOnDelete();
            $table->string('thread_id', 191)->nullable()->after('provider_message_id');
            $table->string('rfc_message_id', 512)->nullable();
            $table->string('in_reply_to', 512)->nullable();
            $table->text('references_header')->nullable();
            $table->json('bcc_recipients')->nullable();
            $table->string('delivery_status', 20)->default('Received');
            $table->timestamp('sent_at')->nullable();
            $table->text('provider_error')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->index(['mailbox_connection_id', 'thread_id', 'occurred_at'], 'email_activities_gmail_thread_index');
            $table->unique('idempotency_key', 'email_activities_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('email_activities', function (Blueprint $table): void {
            $table->dropUnique('email_activities_idempotency_unique');
            $table->dropIndex('email_activities_gmail_thread_index');
            $table->dropForeign(['mailbox_connection_id']);
            $table->dropColumn('mailbox_connection_id');
            $table->dropColumn(['thread_id', 'rfc_message_id', 'in_reply_to', 'references_header', 'bcc_recipients', 'delivery_status', 'sent_at', 'provider_error', 'idempotency_key']);
        });
    }
};

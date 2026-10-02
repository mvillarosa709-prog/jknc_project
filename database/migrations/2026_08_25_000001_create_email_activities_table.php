<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_activities', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 40);
            $table->string('mailbox_key', 191);
            $table->string('provider_message_id', 191);
            $table->foreignId('mailbox_owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject')->nullable();
            $table->string('sender_email');
            $table->json('recipients');
            $table->json('cc_recipients')->nullable();
            $table->string('direction', 20);
            $table->timestamp('occurred_at');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('handling', 40)->default('Linked');
            $table->boolean('has_attachments')->default(false);
            $table->json('attachment_metadata')->nullable();
            $table->json('technical_metadata')->nullable();
            $table->text('client_report_description')->nullable();
            $table->boolean('include_in_client_report')->default(true);
            $table->boolean('include_body_in_client_report')->default(false);
            $table->timestamps();

            $table->unique(
                ['provider', 'mailbox_key', 'provider_message_id'],
                'email_activities_provider_message_unique'
            );
            $table->index(['company_id', 'occurred_at']);
            $table->index(['contact_id', 'occurred_at']);
            $table->index(['mailbox_owner_user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_activities');
    }
};

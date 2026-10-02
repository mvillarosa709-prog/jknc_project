<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_links', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->string('related_type', 40);
            $table->unsignedBigInteger('related_id');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['source_type', 'source_id', 'related_type', 'related_id'],
                'activity_links_unique_pair'
            );
            $table->index(['source_type', 'source_id'], 'activity_links_source_index');
            $table->index(['related_type', 'related_id'], 'activity_links_related_index');
        });

        Schema::create('activity_attachments', function (Blueprint $table): void {
            $table->id();
            $table->string('activity_type', 40);
            $table->unsignedBigInteger('activity_id');
            $table->string('filename');
            $table->string('original_filename')->nullable();
            $table->string('storage_disk', 40)->nullable();
            $table->string('storage_path')->nullable();
            $table->text('external_url')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('category', 60)->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['activity_type', 'activity_id'], 'activity_attachments_source_index');
        });

        Schema::create('activity_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('activity_type', 40);
            $table->unsignedBigInteger('activity_id')->nullable();
            $table->string('action', 80);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('changes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['activity_type', 'activity_id', 'created_at'], 'activity_audit_source_index');
        });

        Schema::table('meetings', function (Blueprint $table): void {
            $table->json('internal_participant_user_ids')->nullable();
            $table->json('external_attendees')->nullable();
            $table->json('structured_minutes')->nullable();
            $table->timestamp('finalized_minutes_at')->nullable();
            $table->foreignId('finalized_minutes_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('recording_metadata')->nullable();
            $table->json('transcript_metadata')->nullable();
            $table->json('files_metadata')->nullable();
        });

        Schema::create('meeting_action_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->text('description');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 40)->default('Open');
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['meeting_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_action_items');

        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('finalized_minutes_by_user_id');
            $table->dropColumn([
                'internal_participant_user_ids',
                'external_attendees',
                'structured_minutes',
                'finalized_minutes_at',
                'recording_metadata',
                'transcript_metadata',
                'files_metadata',
            ]);
        });

        Schema::dropIfExists('activity_audit_logs');
        Schema::dropIfExists('activity_attachments');
        Schema::dropIfExists('activity_links');
    }
};

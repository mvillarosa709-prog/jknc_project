<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->unsignedInteger('manual_actual_duration_minutes')
                ->nullable()
                ->after('actual_duration_minutes');
        });

        DB::table('tasks')
            ->whereNotNull('actual_duration_minutes')
            ->update(['manual_actual_duration_minutes' => DB::raw('actual_duration_minutes')]);

        foreach (['events', 'calls', 'meetings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'duration_minutes')) {
                    $table->unsignedInteger('duration_minutes')->nullable();
                }
            });
        }

        Schema::table('message_conversations', function (Blueprint $table): void {
            $table->text('conversation_summary')->nullable();
            $table->string('direction', 40)->nullable();
            $table->json('participant_contact_ids')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
        });

        Schema::table('official_business_trips', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->unsignedInteger('duration_minutes')->nullable();
        });

        Schema::table('meetings', function (Blueprint $table): void {
            $table->string('calendar_provider', 40)->nullable();
            $table->string('external_calendar_event_id')->nullable();
            $table->text('conference_url')->nullable();
            $table->string('calendar_sync_status', 40)->nullable();
            $table->timestamp('calendar_synced_at')->nullable();
            $table->text('calendar_sync_error')->nullable();
            $table->boolean('notify_external_attendees')->default(false);
            $table->json('external_notification_recipient_ids')->nullable();

            $table->index(
                ['calendar_provider', 'external_calendar_event_id'],
                'meetings_external_calendar_index'
            );
        });

        Schema::create('meeting_minutes_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('content');
            $table->string('status', 20)->default('DRAFT');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['meeting_id', 'version_number'], 'meeting_minutes_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_minutes_versions');

        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropIndex('meetings_external_calendar_index');
            $table->dropColumn([
                'calendar_provider',
                'external_calendar_event_id',
                'conference_url',
                'calendar_sync_status',
                'calendar_synced_at',
                'calendar_sync_error',
                'notify_external_attendees',
                'external_notification_recipient_ids',
            ]);
        });

        Schema::table('official_business_trips', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('project_id');
            $table->dropConstrainedForeignId('deal_id');
            $table->dropConstrainedForeignId('contact_id');
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('duration_minutes');
        });

        Schema::table('message_conversations', function (Blueprint $table): void {
            $table->dropColumn([
                'conversation_summary',
                'direction',
                'participant_contact_ids',
                'started_at',
                'ended_at',
                'duration_minutes',
            ]);
        });

        foreach (['events', 'calls', 'meetings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('duration_minutes');
            });
        }

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('manual_actual_duration_minutes');
        });
    }
};

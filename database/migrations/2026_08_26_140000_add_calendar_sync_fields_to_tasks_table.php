<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->string('calendar_provider', 40)->nullable();
            $table->string('external_calendar_event_id')->nullable();
            $table->string('calendar_sync_status', 40)->nullable();
            $table->timestamp('calendar_synced_at')->nullable();
            $table->text('calendar_sync_error')->nullable();

            $table->index(
                ['calendar_provider', 'external_calendar_event_id'],
                'tasks_external_calendar_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex('tasks_external_calendar_index');
            $table->dropColumn([
                'calendar_provider',
                'external_calendar_event_id',
                'calendar_sync_status',
                'calendar_synced_at',
                'calendar_sync_error',
            ]);
        });
    }
};

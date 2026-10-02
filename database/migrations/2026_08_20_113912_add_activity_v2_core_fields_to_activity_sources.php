<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'tasks',
            'events',
            'calls',
            'meetings',
            'message_conversations',
            'official_business_trips',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'prepared_by_user_id')) {
                    $table->foreignId('prepared_by_user_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn($tableName, 'client_report_description')) {
                    $table->text('client_report_description')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'include_in_client_report')) {
                    $table->boolean('include_in_client_report')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'tasks',
            'events',
            'calls',
            'meetings',
            'message_conversations',
            'official_business_trips',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'prepared_by_user_id')) {
                    $table->dropConstrainedForeignId('prepared_by_user_id');
                }

                if (Schema::hasColumn($tableName, 'client_report_description')) {
                    $table->dropColumn('client_report_description');
                }

                if (Schema::hasColumn($tableName, 'include_in_client_report')) {
                    $table->dropColumn('include_in_client_report');
                }
            });
        }
    }
};
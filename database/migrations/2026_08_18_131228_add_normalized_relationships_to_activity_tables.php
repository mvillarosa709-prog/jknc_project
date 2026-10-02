<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['tasks', 'events', 'calls', 'meetings'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'company_id')) {
                    $table->foreignId('company_id')
                        ->nullable()
                        ->constrained('companies')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn($tableName, 'contact_id')) {
                    $table->foreignId('contact_id')
                        ->nullable()
                        ->constrained('contacts')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn($tableName, 'deal_id')) {
                    $table->foreignId('deal_id')
                        ->nullable()
                        ->constrained('deals')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn($tableName, 'project_id')) {
                    $table->foreignId('project_id')
                        ->nullable()
                        ->constrained('projects')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['tasks', 'events', 'calls', 'meetings'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach ([
                    'project_id',
                    'deal_id',
                    'contact_id',
                    'company_id',
                ] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
            });
        }
    }
};
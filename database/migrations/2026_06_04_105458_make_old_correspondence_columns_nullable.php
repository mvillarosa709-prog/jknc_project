<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function columnExists(string $column): bool
    {
        return Schema::hasColumn('correspondences', $column);
    }

    public function up(): void
    {
        if (!Schema::hasTable('correspondences')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if ($this->columnExists('uploaded_date')) {
            DB::statement("ALTER TABLE correspondences MODIFY uploaded_date DATE NULL");
        }

        if ($this->columnExists('date')) {
            DB::statement("ALTER TABLE correspondences MODIFY date DATE NULL");
        }

        if ($this->columnExists('deadline')) {
            DB::statement("ALTER TABLE correspondences MODIFY deadline DATE NULL");
        }

        if ($this->columnExists('time')) {
            DB::statement("ALTER TABLE correspondences MODIFY time TIME NULL");
        }

        foreach (
            [
                'user',
                'tin',
                'subject',
                'sender_type',
                'sender',
                'department',
                'sent_via',
                'workflow_status',
                'approval_status',
                'review_note',
                'attachment',
            ] as $column
        ) {
            if ($this->columnExists($column)) {
                DB::statement("ALTER TABLE correspondences MODIFY {$column} VARCHAR(255) NULL");
            }
        }

        if ($this->columnExists('details')) {
            DB::statement("ALTER TABLE correspondences MODIFY details TEXT NULL");
        }

        if ($this->columnExists('submitted_by')) {
            DB::statement("ALTER TABLE correspondences MODIFY submitted_by BIGINT UNSIGNED NULL");
        }

        if ($this->columnExists('approved_by')) {
            DB::statement("ALTER TABLE correspondences MODIFY approved_by BIGINT UNSIGNED NULL");
        }

        if ($this->columnExists('approved_at')) {
            DB::statement("ALTER TABLE correspondences MODIFY approved_at TIMESTAMP NULL");
        }
    }

    public function down(): void
    {
        //
    }
};

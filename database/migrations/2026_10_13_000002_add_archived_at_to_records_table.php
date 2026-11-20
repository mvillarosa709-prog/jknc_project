<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a Record was archived.
 *
 * The status alone says whether a Record is archived but not when it happened,
 * and the Archive listing leads on that date. It is stamped when archiving and
 * cleared when restoring, so the column always agrees with the status next to it.
 *
 * The audit trail remains the authority on what happened and why; this column
 * exists so the listing can order by it without reading every log row, and it is
 * written in the same transaction as the status it describes.
 *
 * No index is added. The existing (tenant_id, company_id, status) already narrows
 * the page to archived rows, and ordering that already-small set by a timestamp
 * is cheaper than carrying a second, overlapping B-tree on every write.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('filed_at');
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });
    }
};

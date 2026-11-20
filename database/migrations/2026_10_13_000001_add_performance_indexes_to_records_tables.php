<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Query-supporting indexes for the register, the drawers, and the exports.
 *
 * Only genuinely missing coverage is added here. The tenant/company/status/
 * document_reference indexes these tables are read through already exist from
 * the create migrations:
 *
 *   records             (tenant_id, company_id)
 *                       (tenant_id, company_id, status)
 *                       (tenant_id, company_id, record_classification_id, record_index_id)
 *                       (tenant_id, company_id, document_date)
 *                       UNIQUE (tenant_id, company_id, document_reference)
 *   record_files        (tenant_id, company_id)
 *                       (tenant_id, company_id, record_id)
 *                       (tenant_id, company_id, record_intake_item_id)
 *                       (tenant_id, company_id, ocr_status)
 *                       (tenant_id, company_id, checksum_sha256)
 *   record_activity_logs (tenant_id, company_id)
 *                       (tenant_id, company_id, record_id)
 *                       (tenant_id, company_id, created_at)
 *                       (tenant_id, company_id, action)
 *   record_links        (tenant_id, company_id)
 *                       (tenant_id, company_id, record_id)
 *                       (tenant_id, company_id, linked_type, linked_id)
 *
 * Recreating those as compound indexes would add a second B-tree to maintain on
 * every insert and update without making any query faster, so they are left as
 * they are. The gap they leave is chronological access: both tables are ordered
 * and paged by their own timestamps, and neither had a compound index leading
 * with created_at.
 *
 * id trails created_at so the secondary sort the register already applies
 * (latest document_reference, then latest id) is covered by the same index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'company_id', 'created_at', 'id'],
                'records_tenant_company_created_idx',
            );
        });

        Schema::table('record_files', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'company_id', 'created_at', 'id'],
                'record_files_tenant_company_created_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table): void {
            $table->dropIndex('records_tenant_company_created_idx');
        });

        Schema::table('record_files', function (Blueprint $table): void {
            $table->dropIndex('record_files_tenant_company_created_idx');
        });
    }
};

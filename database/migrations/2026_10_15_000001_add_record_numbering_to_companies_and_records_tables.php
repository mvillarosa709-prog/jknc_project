<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // The Record Numbering tab's settings. The Document Reference is a
            // separate, system-controlled identifier and none of these four
            // columns touch it.
            $table->string('record_prefix', 10)->default('REC')->after('storage_limit_bytes');
            $table->unsignedTinyInteger('record_sequence_digits')->default(6)->after('record_prefix');
            $table->unsignedBigInteger('record_next_sequence')->default(1)->after('record_sequence_digits');
            $table->boolean('record_include_year')->default(true)->after('record_next_sequence');
        });

        Schema::table('records', function (Blueprint $table) {
            // Nullable on purpose: every record filed before Record IDs existed
            // has none, and backfilling would mint identifiers that were never
            // issued while silently consuming the sequence.
            $table->string('record_number', 40)->nullable()->after('document_reference');

            // The authoritative uniqueness guarantee. RecordFilingService locks
            // the company row so the counter cannot collide in practice, but an
            // operator can set Next Sequence backwards, and this index is what
            // actually stops two records sharing a number.
            $table->unique(
                ['tenant_id', 'company_id', 'record_number'],
                'records_tenant_company_record_number_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropUnique('records_tenant_company_record_number_unique');
            $table->dropColumn('record_number');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['record_prefix', 'record_sequence_digits', 'record_next_sequence', 'record_include_year']);
        });
    }
};

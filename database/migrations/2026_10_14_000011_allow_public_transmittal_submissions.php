<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transmittals', function (Blueprint $table) {
            $table->foreignId('prepared_by')->nullable()->change();
        });

        Schema::table('transmittal_documents', function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('transmittals')->whereNull('prepared_by')->exists()
            || DB::table('transmittal_documents')->whereNull('uploaded_by')->exists()) {
            throw new RuntimeException('Public submissions exist; their submitter cannot be restored during rollback.');
        }

        Schema::table('transmittals', function (Blueprint $table) {
            $table->foreignId('prepared_by')->nullable(false)->change();
        });

        Schema::table('transmittal_documents', function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable(false)->change();
        });
    }
};

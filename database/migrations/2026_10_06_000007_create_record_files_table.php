<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->string('company_id', 36)->index();
            $table->foreignUlid('record_id')
                ->nullable()->constrained('records')->cascadeOnDelete();
            $table->foreignUlid('record_intake_item_id')
                ->nullable()->constrained('record_intake_items')->cascadeOnDelete();

            $table->string('file_role', 30)->default('supporting');
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('checksum_sha256', 64)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('ocr_status', 30)->default('pending');
            $table->text('ocr_text')->nullable();
            $table->text('ocr_error')->nullable();
            $table->timestamp('ocr_completed_at')->nullable();
            $table->foreignUlid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'company_id', 'record_id']);
            $table->index(['tenant_id', 'company_id', 'record_intake_item_id']);
            $table->index(['tenant_id', 'company_id', 'ocr_status']);
            $table->index(['tenant_id', 'company_id', 'checksum_sha256'], 'record_files_tenant_company_checksum_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_files');
    }
};

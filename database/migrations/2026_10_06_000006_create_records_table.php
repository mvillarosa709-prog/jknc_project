<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->string('company_id', 36)->index();
            $table->foreignUlid('record_classification_id')
                ->constrained('record_classifications')->restrictOnDelete();
            $table->foreignUlid('record_index_id')
                ->constrained('record_indexes')->restrictOnDelete();
            $table->foreignUlid('record_intake_item_id')
                ->nullable()->constrained('record_intake_items')->nullOnDelete();

            $table->string('document_reference', 5)->nullable();
            $table->string('title');
            $table->date('document_date')->nullable();
            $table->string('source')->nullable();
            $table->string('external_reference_number')->nullable();

            $table->json('identifiers')->nullable();
            $table->json('additional_dates')->nullable();
            $table->json('signatories')->nullable();
            $table->json('financials')->nullable();

            $table->string('status', 30)->default('filed');
            $table->unsignedSmallInteger('record_units')->default(1);
            $table->unsignedBigInteger('storage_bytes')->default(0);
            $table->string('retention_period', 50)->nullable();
            $table->date('retention_until')->nullable();
            $table->boolean('is_confidential')->default(false);
            $table->timestamp('filed_at')->nullable();
            $table->string('filed_by', 36)->nullable()->index();
            $table->string('created_by', 36)->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'company_id', 'status']);
            $table->index(['tenant_id', 'company_id', 'record_classification_id', 'record_index_id'], 'records_tenant_company_class_index_idx');
            $table->index(['tenant_id', 'company_id', 'document_date']);
            $table->unique(['tenant_id', 'company_id', 'document_reference'], 'records_tenant_company_doc_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('records');
    }
};

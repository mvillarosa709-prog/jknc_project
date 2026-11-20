<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_intake_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->string('company_id', 36)->index();
            $table->string('title');
            $table->string('source')->nullable();
            $table->date('document_date')->nullable();
            $table->string('external_reference_number')->nullable();
            $table->foreignUlid('suggested_classification_id')
                ->nullable()->constrained('record_classifications')->nullOnDelete();
            $table->foreignUlid('suggested_index_id')
                ->nullable()->constrained('record_indexes')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->string('capture_source', 30)->default('upload');
            $table->json('ocr_result')->nullable();
            $table->timestamp('ocr_completed_at')->nullable();
            $table->unsignedBigInteger('storage_bytes')->default(0);
            $table->foreignUlid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('filed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'company_id', 'status']);
            $table->index(['tenant_id', 'company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_intake_items');
    }
};

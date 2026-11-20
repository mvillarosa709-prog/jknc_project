<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_indexes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->string('company_id', 36)->index();
            $table->foreignUlid('record_classification_id')
                ->constrained('record_classifications')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('advanced_field_config')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'company_id', 'record_classification_id'], 'record_indexes_tenant_company_class_idx');
            $table->index(['tenant_id', 'company_id', 'is_active']);
            $table->unique(['tenant_id', 'company_id', 'record_classification_id', 'code'], 'record_indexes_tenant_company_class_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_indexes');
    }
};

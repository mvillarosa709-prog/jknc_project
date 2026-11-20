<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->string('company_id', 36)->index();
            $table->foreignUlid('record_id')->constrained('records')->cascadeOnDelete();
            $table->string('linked_type');
            $table->ulid('linked_id');
            $table->string('link_type', 40)->default('related');
            $table->text('notes')->nullable();
            $table->string('created_by', 36)->nullable()->index();
            $table->timestamps();

            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'company_id', 'record_id']);
            $table->index(['tenant_id', 'company_id', 'linked_type', 'linked_id'], 'record_links_tenant_company_linked_idx');
            $table->unique(['tenant_id', 'company_id', 'record_id', 'linked_type', 'linked_id'], 'record_links_unique_link_per_record');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_links');
    }
};

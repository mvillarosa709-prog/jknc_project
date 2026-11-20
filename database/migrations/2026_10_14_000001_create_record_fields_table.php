<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_fields', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->string('company_id', 36)->index();
            $table->string('name');
            $table->string('type', 50)->default('Text');
            $table->boolean('is_filterable')->default(false);
            $table->string('status', 30)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'company_id', 'status']);
            // Names are unique per company rather than globally: two companies
            // may each register their own "Client Code" without colliding.
            $table->unique(['tenant_id', 'company_id', 'name'], 'record_fields_tenant_company_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_fields');
    }
};

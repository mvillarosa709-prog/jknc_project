<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_activity_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->string('company_id', 36)->index();
            $table->foreignUlid('record_id')->nullable()->constrained('records')->cascadeOnDelete();
            $table->foreignUlid('record_intake_item_id')
                ->nullable()->constrained('record_intake_items')->cascadeOnDelete();
            $table->foreignUlid('record_file_id')
                ->nullable()->constrained('record_files')->cascadeOnDelete();
            $table->string('user_id', 36)->nullable()->index();
            $table->string('action', 60);
            $table->text('description')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'company_id', 'record_id']);
            $table->index(['tenant_id', 'company_id', 'created_at']);
            $table->index(['tenant_id', 'company_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_activity_logs');
    }
};

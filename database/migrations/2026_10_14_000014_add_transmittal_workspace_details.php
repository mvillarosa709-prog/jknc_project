<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transmittals', function (Blueprint $table) {
            $table->string('delivery_type')->default('electronic');
            $table->json('delivery_actions')->nullable();
            $table->string('prepared_by_name')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->string('operations_manager')->nullable();
            $table->string('delivered_by')->nullable();
            $table->string('received_by')->nullable();
            $table->string('processing_company')->nullable();
        });

        Schema::create('transmittal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transmittal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('transmittal_documents')->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('particular')->nullable();
            $table->string('unique_id')->nullable();
            $table->string('quantity', 40)->nullable();
            $table->text('description')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['transmittal_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transmittal_items');

        Schema::table('transmittals', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_type',
                'delivery_actions',
                'prepared_by_name',
                'prepared_at',
                'approved_by_name',
                'operations_manager',
                'delivered_by',
                'received_by',
                'processing_company',
            ]);
        });
    }
};

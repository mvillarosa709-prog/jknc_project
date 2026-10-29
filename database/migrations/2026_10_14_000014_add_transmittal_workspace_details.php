<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transmittals')) {
            Schema::table('transmittals', function (Blueprint $table) {
                if (!Schema::hasColumn('transmittals', 'delivery_type')) {
                    $table->string('delivery_type')->default('electronic');
                }
                if (!Schema::hasColumn('transmittals', 'delivery_actions')) {
                    $table->json('delivery_actions')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'prepared_by_name')) {
                    $table->string('prepared_by_name')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'prepared_at')) {
                    $table->timestamp('prepared_at')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'approved_by_name')) {
                    $table->string('approved_by_name')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'operations_manager')) {
                    $table->string('operations_manager')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'delivered_by')) {
                    $table->string('delivered_by')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'received_by')) {
                    $table->string('received_by')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'processing_company')) {
                    $table->string('processing_company')->nullable();
                }
            });
        }

        if (Schema::hasTable('transmittal_items')) {
            Schema::table('transmittal_items', function (Blueprint $table) {
                if (!Schema::hasColumn('transmittal_items', 'document_id')) {
                    $table->foreignId('document_id')->nullable()->constrained('transmittal_documents')->nullOnDelete();
                }
                if (!Schema::hasColumn('transmittal_items', 'position')) {
                    $table->unsignedSmallInteger('position')->default(1);
                }
                if (!Schema::hasColumn('transmittal_items', 'particular')) {
                    $table->string('particular')->nullable();
                }
                if (!Schema::hasColumn('transmittal_items', 'unique_id')) {
                    $table->string('unique_id')->nullable();
                }
                if (!Schema::hasColumn('transmittal_items', 'quantity')) {
                    $table->string('quantity', 40)->nullable();
                }
                if (!Schema::hasColumn('transmittal_items', 'description')) {
                    $table->text('description')->nullable();
                }
                if (!Schema::hasColumn('transmittal_items', 'remarks')) {
                    $table->text('remarks')->nullable();
                }
            });
        } else {
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

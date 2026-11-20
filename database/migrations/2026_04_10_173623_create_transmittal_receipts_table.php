<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transmittal_receipts')) {
            Schema::create('transmittal_receipts', function (Blueprint $table) {
                $table->id();

                $table->foreignId('transmittal_id')->constrained('transmittals')->cascadeOnDelete();

                $table->string('receipt_no')->unique();
                $table->date('receipt_date')->nullable();

                $table->string('mode')->nullable();
                $table->string('from_name')->nullable();
                $table->string('to_name')->nullable();
                $table->string('office_name')->nullable();

                $table->string('delivery_type')->nullable();
                $table->string('delivery_detail')->nullable();
                $table->string('recipient_email')->nullable();
                $table->text('actions_summary')->nullable();

                $table->string('prepared_by_name')->nullable();
                $table->string('approved_by_name')->nullable();
                $table->string('approved_position')->nullable();
                $table->string('document_custodian')->nullable();
                $table->string('delivered_by')->nullable();
                $table->string('received_by')->nullable();
                $table->dateTime('received_at')->nullable();

                $table->unsignedBigInteger('generated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transmittal_receipts');
    }
};
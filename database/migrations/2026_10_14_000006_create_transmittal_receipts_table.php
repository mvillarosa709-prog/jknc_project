<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transmittal_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transmittal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('transmittal_recipients')->cascadeOnDelete();
            $table->string('signed_name');
            $table->text('comments')->nullable();
            $table->string('receipt_code')->unique();
            $table->json('snapshot');
            $table->timestamp('received_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->unique('recipient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transmittal_receipts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transmittal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('transmittal_recipients')->cascadeOnDelete();
            $table->foreignId('receipt_id')->nullable()->constrained('transmittal_receipts')->nullOnDelete();
            $table->string('event');
            $table->json('details')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['recipient_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_acknowledgments');
    }
};

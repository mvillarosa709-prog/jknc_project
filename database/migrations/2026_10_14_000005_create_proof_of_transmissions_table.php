<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proof_of_transmissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transmittal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('transmittal_recipients')->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel')->default('email');
            $table->string('status')->default('queued')->index();
            $table->string('provider_message_id')->nullable()->index();
            $table->timestamp('transmitted_at')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proof_of_transmissions');
    }
};

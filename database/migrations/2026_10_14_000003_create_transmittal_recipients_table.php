<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transmittal_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transmittal_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('email');
            $table->string('organization')->nullable();
            $table->string('acknowledgment_token_hash', 64)->nullable()->unique();
            $table->timestamp('acknowledgment_expires_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['transmittal_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transmittal_recipients');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_outbound_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_entry_id')->unique()->constrained()->cascadeOnDelete();
            $table->uuid('request_id')->unique();
            $table->uuid('correlation_id')->unique();
            $table->string('account_id', 80)->nullable();
            $table->string('peer_id', 80)->nullable();
            $table->string('state', 24)->default('queued')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('retry_at')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_outbound_attempts');
    }
};

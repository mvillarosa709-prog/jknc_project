<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->char('fingerprint', 64);
            $table->longText('payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('error_code')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'fingerprint']);
        });
        Schema::create('messaging_provider_peers', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('account_id', 80);
            $table->string('peer_id', 80);
            $table->foreignId('message_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('delivery_watermark')->default(0);
            $table->unsignedBigInteger('read_watermark')->default(0);
            $table->timestamps();
            $table->unique(['provider', 'account_id', 'peer_id'], 'messaging_peer_identity');
        });
        Schema::create('messaging_provider_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('messaging_provider_peer_id')->constrained()->cascadeOnDelete();
            $table->string('external_message_id');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['messaging_provider_peer_id', 'external_message_id'], 'messaging_receipt_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_provider_receipts');
        Schema::dropIfExists('messaging_provider_peers');
        Schema::dropIfExists('messaging_webhook_events');
    }
};

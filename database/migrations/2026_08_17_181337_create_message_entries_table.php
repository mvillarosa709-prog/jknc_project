<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_conversation_id')
                ->constrained('message_conversations')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('direction', 20);

            $table->string('sender')->nullable();
            $table->string('recipient')->nullable();

            $table->text('body');

            $table->string('delivery_status', 40)->default('Pending');

            $table->string('external_message_id')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();

            $table->json('technical_metadata')->nullable();

            $table->timestamps();

            $table->index('direction');
            $table->index('delivery_status');
            $table->index('sent_at');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_entries');
    }
};

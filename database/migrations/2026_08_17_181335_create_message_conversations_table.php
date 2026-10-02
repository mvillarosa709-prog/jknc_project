<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            $table->foreignId('contact_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();

            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('conversation_title')->nullable();

            $table->string('channel', 40);
            $table->string('line_account')->nullable();

            $table->string('handling', 40)->default('Open');

            $table->boolean('is_linked')->default(true);

            $table->timestamp('last_message_at')->nullable();

            $table->string('external_conversation_id')->nullable();

            $table->json('technical_metadata')->nullable();

            $table->timestamps();

            $table->index('channel');
            $table->index('handling');
            $table->index('is_linked');
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_conversations');
    }
};

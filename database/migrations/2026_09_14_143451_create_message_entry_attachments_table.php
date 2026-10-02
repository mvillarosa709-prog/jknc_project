<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_entry_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_entry_id')
                ->constrained('message_entries')
                ->cascadeOnDelete();

            $table->foreignId('activity_attachment_id')
                ->constrained('activity_attachments')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(
                ['message_entry_id', 'activity_attachment_id'],
                'message_entry_attachments_unique'
            );

            $table->index(
                'activity_attachment_id',
                'message_entry_attachments_attachment_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_entry_attachments');
    }
};
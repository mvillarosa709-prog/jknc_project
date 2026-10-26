<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transmittal_upload_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('upload_id')->unique();
            $table->char('owner_session_hash', 64)->index();
            $table->char('upload_token_hash', 64);
            $table->string('original_name', 255);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('expected_size');
            $table->unsignedInteger('chunk_size');
            $table->unsignedInteger('chunk_count');
            $table->json('chunk_hashes')->nullable();
            $table->string('status')->default('uploading')->index();
            $table->string('storage_path')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transmittal_upload_sessions');
    }
};

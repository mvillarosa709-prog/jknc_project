<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transmittal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transmittal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('category')->default('document')->index();
            $table->string('original_name');
            $table->string('storage_path')->unique();
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->longText('ocr_text')->nullable();
            $table->json('extracted_data')->nullable();
            $table->timestamps();
            $table->index(['transmittal_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transmittal_documents');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transmittals', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->string('subject');
            $table->text('purpose');
            $table->string('confidentiality')->default('standard');
            $table->string('status')->default('draft')->index();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->json('approved_snapshot')->nullable();
            $table->string('approval_authority_code')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transmittals');
    }
};

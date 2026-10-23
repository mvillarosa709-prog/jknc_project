<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_authority_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transmittal_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->string('code')->unique();
            $table->json('frozen_snapshot');
            $table->char('snapshot_hash', 64);
            $table->timestamp('approved_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_authority_codes');
    }
};

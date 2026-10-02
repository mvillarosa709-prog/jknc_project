<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->string('visit_reference')->unique();
            $table->foreignId('visitor_id')->constrained('visitors')->restrictOnDelete();
            $table->string('status');
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->string('activity_type')->nullable();
            $table->unsignedBigInteger('activity_id')->nullable();
            $table->timestamps();

            $table->index(['visitor_id', 'status']);
            $table->index(['activity_type', 'activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};

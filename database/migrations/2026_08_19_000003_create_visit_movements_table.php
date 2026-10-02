<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->string('movement_type');
            $table->timestamp('occurred_at');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
            $table->decimal('accuracy_metres', 10, 2)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->string('source')->nullable();
            $table->string('location_label')->nullable();
            $table->timestamps();

            $table->unique(['visit_id', 'movement_type']);
            $table->index(['branch_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_movements');
    }
};

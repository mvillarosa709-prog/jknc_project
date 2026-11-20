<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transmittal_items')) {
            Schema::create('transmittal_items', function (Blueprint $table) {
                $table->id();

                $table->foreignId('transmittal_id')->constrained('transmittals')->cascadeOnDelete();

                $table->unsignedInteger('item_no')->default(1);
                $table->string('particular')->nullable();
                $table->string('unique_id')->nullable();
                $table->unsignedInteger('qty')->nullable();
                $table->text('description')->nullable();
                $table->text('remarks')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transmittal_items');
    }
};
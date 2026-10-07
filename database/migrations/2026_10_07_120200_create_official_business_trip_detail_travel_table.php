<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_business_trip_detail_travel', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('official_business_trip_detail_id')
                ->unique('ob_trip_detail_travel_detail_unique')
                ->constrained('official_business_trip_details', indexName: 'ob_trip_detail_travel_detail_fk')
                ->cascadeOnDelete();
            $table->date('departure_date')->nullable();
            $table->time('departure_time')->nullable();
            $table->date('expected_arrival_date')->nullable();
            $table->time('expected_arrival_time')->nullable();
            $table->decimal('estimated_expense', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_business_trip_detail_travel');
    }
};

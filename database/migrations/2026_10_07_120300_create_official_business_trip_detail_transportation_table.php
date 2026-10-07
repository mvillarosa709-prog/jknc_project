<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_business_trip_detail_transportation', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('official_business_trip_detail_id')
                ->unique('ob_trip_detail_transport_detail_unique')
                ->constrained('official_business_trip_details', indexName: 'ob_trip_detail_transport_detail_fk')
                ->cascadeOnDelete();
            $table->string('mode', 80)->nullable()->index();
            $table->string('vehicle')->nullable();
            $table->string('plate_number')->nullable();
            $table->string('driver')->nullable();
            $table->string('owner_driver')->nullable();
            $table->string('provider')->nullable();
            $table->string('transport_type')->nullable();
            $table->string('rental_provider')->nullable();
            $table->string('airline')->nullable();
            $table->string('flight_number')->nullable();
            $table->string('shipping_line')->nullable();
            $table->string('vessel_trip')->nullable();
            $table->text('other_details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_business_trip_detail_transportation');
    }
};
